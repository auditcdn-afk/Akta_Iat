<?php

namespace Tests\Feature;

use App\Models\PemeriksaanCekFisik;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nama ketiga kolom blangko Cek Fisik boleh diganti auditor di layar
 * (mis. STUJ -> WO), dan Report Audit HARUS ikut nama itu.
 *
 * Ini bukan soal kerapian. Report Audit dicetak lalu jadi arsip. Kalau
 * auditor mencatat "WO" di layar tapi laporannya tercetak "STUJ", arsip
 * kertasnya tidak cocok dengan yang sebenarnya diperiksa -- dan yang
 * membacanya setahun kemudian tidak punya cara tahu keduanya barang yang
 * sama. Judul bagiannya pun dirakit dari ketiga nama itu, jadi ikut dijaga.
 */
class ReportPdfNamaKolomCekFisikTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create(['username' => 'admin1', 'role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'CSC UJT',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);
    }

    private function simpanCekFisik(array $nama, array $fisik = ['cf' => 0, 'stuj' => 0, 'fstnk' => 0]): void
    {
        PemeriksaanCekFisik::query()->create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'nama'       => $nama,
                'saldoAwal'  => ['tanggal' => '2026-09-01', 'cf' => 10, 'stuj' => 10, 'fstnk' => 10],
                'penerimaan' => [],
                'pengeluaran' => [],
                'fisik'      => $fisik,
            ],
        ]);
    }

    private function html(): string
    {
        return $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();
    }

    public function test_nama_kolom_yang_diganti_ikut_tercetak_di_laporan(): void
    {
        $this->simpanCekFisik(['cf' => '', 'stuj' => 'WO', 'fstnk' => '']);

        $html = $this->html();

        $this->assertStringContainsString('WO', $html);
        $this->assertStringNotContainsString('STUJ', $html,
            'Laporan masih mencetak "STUJ" padahal auditor sudah menggantinya jadi "WO".');
    }

    public function test_judul_bagian_dirakit_dari_ketiga_nama_kolom(): void
    {
        $this->simpanCekFisik(['cf' => 'CF Unit', 'stuj' => 'WO', 'fstnk' => 'STNK']);

        $this->assertStringContainsString(
            '12. CEK FISIK (Blangko CF Unit, WO &amp; STNK)',
            $this->html(),
        );
    }

    public function test_nama_kosong_kembali_ke_nama_bawaan(): void
    {
        // Middleware Laravel mengubah string kosong jadi null sebelum tersimpan,
        // jadi null harus diperlakukan sama dengan "pakai nama bawaan".
        $this->simpanCekFisik(['cf' => null, 'stuj' => '   ', 'fstnk' => null]);

        $html = $this->html();

        $this->assertStringContainsString('12. CEK FISIK (Blangko Cek Fisik (CF), STUJ &amp; F. STNK)', $html);
    }

    public function test_pemeriksaan_lama_tanpa_nama_tetap_terbaca(): void
    {
        // Data yang tersimpan sebelum fitur ini ada sama sekali tidak punya
        // kunci 'nama'. Laporannya harus tetap tercetak, bukan error.
        PemeriksaanCekFisik::query()->create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'saldoAwal'   => ['tanggal' => '2026-09-01', 'cf' => 5, 'stuj' => 5, 'fstnk' => 5],
                'penerimaan'  => [],
                'pengeluaran' => [],
                'fisik'       => ['cf' => 5, 'stuj' => 5, 'fstnk' => 5],
            ],
        ]);

        $this->assertStringContainsString('12. CEK FISIK (Blangko Cek Fisik (CF), STUJ &amp; F. STNK)', $this->html());
    }

    public function test_peringatan_selisih_memakai_nama_yang_diganti(): void
    {
        // Saldo awal 10, fisik 5 -> selisih 5 pada kolom yang dinamai ulang.
        $this->simpanCekFisik(['cf' => '', 'stuj' => 'WO', 'fstnk' => ''],
            ['cf' => 10, 'stuj' => 5, 'fstnk' => 10]);

        $this->assertStringContainsString('WO: +5', $this->html());
    }
}
