<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanTtpGantung;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tool "TTP Gantung": hasil impor .html kadang memuat data yang salah, dan
 * auditor harus bisa memperbaikinya langsung di tabel (mirip keterangan di
 * tool Meterai) tanpa menunggu berkasnya dibetulkan di sistem sumber.
 *
 * Perbaikannya disimpan apa adanya di ttp_json bersama jejak nilai aslinya
 * (asliImpor), supaya tetap ketahuan mana angka hasil impor dan mana yang
 * diperbaiki tangan.
 */
class TtpGantungEditTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'PRW MTR',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'ttp-gantung',
            'nama_auditor'  => 'AUDITOR SATU',
            'nama_auditee'  => 'AUDITEE SATU',
        ]);
    }

    private function baris(array $ubah = []): array
    {
        return array_merge([
            'leasing'    => 'ADIRA',
            'noTtp'      => 'FC002248',
            'tglTtp'     => '2025-12-26',
            'noFaktur'   => '0273/PRW/XII/2025',
            'nama'       => 'SAMSI IRAWAN',
            'nilai'      => 167182.0,
            'sudahCair'  => 0.0,
            'pencTgl'    => '',
            'pencNilai'  => 0.0,
            'belumCair'  => 167182.0,
            'keterangan' => 'a/n : SAMSI IRAWAN',
            'fisik'      => false,
        ], $ubah);
    }

    private function simpan(array $ttp)
    {
        return $this->postJson('/api/audit-detail/ttp-gantung', [
            'planAuditId' => $this->plan->id,
            'tglAudit'    => '2026-09-18',
            'ttp'         => $ttp,
        ]);
    }

    /** Sel yang diperbaiki auditor tersimpan LENGKAP dengan jejak nilai aslinya. */
    public function test_perbaikan_manual_tersimpan_bersama_jejak_nilai_asli(): void
    {
        $this->simpan([$this->baris([
            'nama'      => 'SAMSI IRAWAN',           // dibiarkan
            'nilai'     => 176182.0,                 // diperbaiki dari 167182
            'belumCair' => 176182.0,                 // ikut terhitung ulang
            'asliImpor' => ['nilai' => 167182.0, 'belumCair' => 167182.0],
        ])])->assertOk();

        $res = $this->getJson("/api/audit-detail/ttp-gantung?plan_audit_id={$this->plan->id}")->assertOk();
        $row = $res->json('data.ttp.0');

        $this->assertSame(176182.0, (float) $row['nilai']);
        $this->assertSame(176182.0, (float) $row['belumCair']);
        $this->assertSame(167182.0, (float) $row['asliImpor']['nilai'], 'Nilai hasil impor harus tetap tercatat.');
        $this->assertSame(167182.0, (float) $row['asliImpor']['belumCair']);
        $this->assertSame('SAMSI IRAWAN', $row['nama']);
    }

    /** Tanda "diisi manual" pada Tagihan Belum Cair ikut tersimpan. */
    public function test_penanda_belum_cair_diisi_manual_ikut_tersimpan(): void
    {
        $this->simpan([$this->baris([
            'belumCair'        => 100000.0,
            'belumCairManual'  => true,
            'asliImpor'        => ['belumCair' => 167182.0],
        ])])->assertOk();

        $row = $this->getJson("/api/audit-detail/ttp-gantung?plan_audit_id={$this->plan->id}")
            ->json('data.ttp.0');

        $this->assertTrue($row['belumCairManual']);
        $this->assertSame(100000.0, (float) $row['belumCair']);
    }

    /** Baris yang dihapus auditor benar-benar hilang dari data tersimpan. */
    public function test_baris_yang_dihapus_tidak_tersimpan_lagi(): void
    {
        $this->simpan([
            $this->baris(),
            $this->baris(['noTtp' => 'FD003747', 'nama' => 'RANI ELITIS']),
        ])->assertOk();

        $this->assertCount(2, PemeriksaanTtpGantung::where('plan_audit_id', $this->plan->id)->first()->ttp_json);

        $this->simpan([$this->baris(['noTtp' => 'FD003747', 'nama' => 'RANI ELITIS'])])->assertOk();

        $tersimpan = PemeriksaanTtpGantung::where('plan_audit_id', $this->plan->id)->first()->ttp_json;
        $this->assertCount(1, $tersimpan);
        $this->assertSame('FD003747', $tersimpan[0]['noTtp']);
    }

    /** Nama leasing pun boleh diperbaiki — kelompoknya ikut berganti. */
    public function test_nama_leasing_bisa_diperbaiki(): void
    {
        $this->simpan([
            $this->baris(['leasing' => 'ADIRA FINANCE', 'asliImpor' => ['leasing' => 'ADIRA']]),
            $this->baris(['noTtp' => 'FD003747', 'leasing' => 'ADIRA FINANCE', 'asliImpor' => ['leasing' => 'ADIRA']]),
        ])->assertOk();

        $tersimpan = PemeriksaanTtpGantung::where('plan_audit_id', $this->plan->id)->first()->ttp_json;
        $this->assertSame('ADIRA FINANCE', $tersimpan[0]['leasing']);
        $this->assertSame('ADIRA FINANCE', $tersimpan[1]['leasing']);
        $this->assertSame('ADIRA', $tersimpan[1]['asliImpor']['leasing']);
    }

    /**
     * Hasil parse berkas .html harus langsung bisa disimpan apa adanya —
     * bentuk barisnya sama persis dengan yang dipakai tabel yang bisa diedit.
     */
    public function test_hasil_parse_html_bisa_langsung_disimpan(): void
    {
        $html = <<<'HTML'
        <table>
          <tr><td></td><td colspan="6"><b>ADIRA</b></td></tr>
          <tr>
            <td>1</td><td>FC002248</td><td>26-12-2025</td><td>0273/PRW/XII/2025</td>
            <td>SAMSI IRAWAN</td><td>167.182</td><td>0</td><td>-</td><td>0</td>
            <td>167.182</td><td>a/n : SAMSI IRAWAN REFUND</td>
          </tr>
        </table>
        HTML;
        $path = sys_get_temp_dir() . '/' . uniqid('ttp_') . '.html';
        file_put_contents($path, $html);

        $parsed = $this->postJson('/api/audit-detail/ttp-gantung/parse-html', [
            'file' => new UploadedFile($path, 'ttp.html', null, null, true),
        ])->assertOk()->json('data');

        $this->assertCount(1, $parsed);
        $this->assertSame('FC002248', $parsed[0]['noTtp']);
        $this->assertSame('2025-12-26', $parsed[0]['tglTtp']);
        $this->assertSame('ADIRA', $parsed[0]['leasing']);

        // Baris hasil impor diperbaiki sedikit, lalu disimpan.
        $parsed[0]['nama']      = 'SAMSI IRAWAN S.';
        $parsed[0]['asliImpor'] = ['nama' => 'SAMSI IRAWAN'];

        $this->simpan($parsed)->assertOk();

        $row = $this->getJson("/api/audit-detail/ttp-gantung?plan_audit_id={$this->plan->id}")
            ->json('data.ttp.0');
        $this->assertSame('SAMSI IRAWAN S.', $row['nama']);
        $this->assertSame('SAMSI IRAWAN', $row['asliImpor']['nama']);
    }

    /**
     * Berkas TTP Gantung berbentuk HTML, jadi angkanya datang sebagai teks yang
     * sudah diformat. "167.182" pada laporan berarti seratus enam puluh tujuh
     * ribu, bukan 167,182 rupiah — kalau tertukar, seluruh nilai tagihan
     * mengecil seperseribu tanpa ada tanda apa pun bahwa datanya salah.
     *
     */
    #[DataProvider('bentukAngka')]
    public function test_pemisah_ribuan_terbaca_benar(string $tulisan, float $harap): void
    {
        $html = '<table><tr><td></td><td colspan="6"><b>ADIRA</b></td></tr>'
            . '<tr><td>1</td><td>FC002248</td><td>26-12-2025</td><td>0273/PRW/XII/2025</td>'
            . '<td>SAMSI IRAWAN</td><td>' . $tulisan . '</td><td>0</td><td>-</td><td>0</td>'
            . '<td>' . $tulisan . '</td><td>-</td></tr></table>';

        $path = sys_get_temp_dir() . '/' . uniqid('ttp_angka_') . '.html';
        file_put_contents($path, $html);

        $row = $this->postJson('/api/audit-detail/ttp-gantung/parse-html', [
            'file' => new UploadedFile($path, 'ttp.html', null, null, true),
        ])->assertOk()->json('data.0');

        $this->assertSame($harap, (float) $row['nilai'], "Angka \"{$tulisan}\" salah terbaca.");
        $this->assertSame($harap, (float) $row['belumCair']);
    }

    public static function bentukAngka(): array
    {
        return [
            'ribuan titik (Indonesia)'   => ['167.182', 167182.0],
            'ribuan koma (Inggris)'      => ['167,182', 167182.0],
            'tanpa pemisah'              => ['167182', 167182.0],
            'jutaan titik'               => ['1.234.567', 1234567.0],
            'jutaan koma'                => ['1,234,567', 1234567.0],
            'ribuan titik + desimal koma'=> ['1.234.567,89', 1234567.89],
            'ribuan koma + desimal titik'=> ['1,234,567.89', 1234567.89],
            'nol'                        => ['0', 0.0],
            'strip'                      => ['-', 0.0],
            'dengan Rp'                  => ['Rp 167.182', 167182.0],
        ];
    }
}
