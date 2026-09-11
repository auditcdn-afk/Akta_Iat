<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Tombol "Export Selisih" di tab Perlengkapan: unduh rekap gabungan
 * perlengkapan per jenis sebagai Excel, tanpa harus mencetak seluruh Report
 * Audit.
 *
 * Angkanya WAJIB sama dengan bagian "C. REKAP GABUNGAN PERLENGKAPAN PER JENIS"
 * di Report Audit — keduanya memakai PerlengkapanOnhand::rekapGabungan().
 */
class PerlengkapanExportSelisihTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor1', 'role' => 'auditor', 'unit_usaha' => 'CVSK H1',
        ]));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0486/01/09/2026/SPT-IAT', 'cabang' => 'CVSK H1',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        DbUnitUsaha::query()->create(['unit_usaha' => 'CVSK H1', 'wilayah' => 'RIAU', 'jenis' => 'H1']);
        DbPerlengkapan::query()->create([
            'kode' => 'JBK1E', 'wilayah' => 'RIAU', 'nama' => 'BeAT',
            'keterangan' => 'Helm Open Face, Toolset Matic',
        ]);
        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'perlengkapan',
            'nama_auditor' => 'Abdul Aziz', 'nama_auditee' => 'Yogi Prabowo',
        ]);

        // 2 unit BeAT: satu helm ketemu, satu tidak. Toolset ketemu keduanya.
        foreach ([['JBK1E1000001', true], ['JBK1E1000002', false]] as [$noMesin, $helmAda]) {
            $smh = PemeriksaanSmh::query()->firstOrCreate(
                ['plan_audit_id' => $this->plan->id],
                ['no_spt' => $this->plan->no_spt, 'cabang' => 'CVSK H1']
            );
            SmhOnhandItem::query()->create([
                'pemeriksaan_smh_id' => $smh->id,
                'no_mesin'           => $noMesin,
                'no_rangka'          => 'MH1' . $noMesin,
                'status_fisik'       => 'ada',
                'perlengkapan_json'  => [
                    ['nama' => 'Helm Open Face', 'ada' => $helmAda],
                    ['nama' => 'Toolset Matic',  'ada' => true],
                ],
            ]);
        }
    }

    /** @return array<string, array<int, mixed>> jenis => baris sheet */
    private function unduh(string $query = ''): array
    {
        $res = $this->get('/api/audit-detail/perlengkapan/export-selisih?plan_audit_id=' . $this->plan->id . $query);
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $rows = IOFactory::load($tmp)->getActiveSheet()->toArray();
        @unlink($tmp);

        $hasil = [];
        foreach ($rows as $row) {
            if (is_numeric($row[0] ?? null) && ! empty($row[1])) $hasil[$row[1]] = $row;
        }

        return $hasil;
    }

    public function test_hanya_jenis_yang_ada_selisih_yang_diunduh(): void
    {
        $baris = $this->unduh();

        // Helm: 2 unit butuh, 1 ketemu => selisih -1, ikut terunduh.
        $this->assertArrayHasKey('Helm Open Face', $baris);
        // Toolset: 2 butuh, 2 ketemu => pas, tidak perlu ditindaklanjuti.
        $this->assertArrayNotHasKey('Toolset Matic', $baris);
    }

    public function test_angka_kolomnya_sesuai_rekap_gabungan(): void
    {
        $helm = $this->unduh()['Helm Open Face'];

        // No, Jenis, SMH Saldo, SMH Fisik, SMH Selisih, Luar Saldo, Luar Fisik,
        // Luar Selisih, Total Selisih, Keterangan
        $this->assertSame(2, (int) $helm[2], 'SMH Saldo (unit yang membutuhkan)');
        $this->assertSame(1, (int) $helm[3], 'SMH Fisik (ditemukan di unit)');
        $this->assertSame(-1, (int) $helm[4], 'SMH Selisih');
        $this->assertSame(1, (int) $helm[5], 'Luar SMH Saldo (buku)');
        $this->assertSame(0, (int) $helm[6], 'Luar SMH Fisik');
        $this->assertSame(-1, (int) $helm[7], 'Luar SMH Selisih');
        $this->assertSame(-1, (int) $helm[8], 'Total Selisih');
    }

    public function test_angkanya_sama_dengan_report_audit(): void
    {
        $helmExcel = $this->unduh()['Helm Open Face'];

        $data = $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->original->getData();
        $helmReport = collect($data['rekapGabungan'] ?? [])->firstWhere('jenis', 'Helm Open Face');

        $this->assertNotNull($helmReport, 'Report Audit harus punya baris Helm Open Face.');
        $this->assertSame((int) $helmReport['smhSaldo'], (int) $helmExcel[2]);
        $this->assertSame((int) $helmReport['smhFisik'], (int) $helmExcel[3]);
        $this->assertSame((int) $helmReport['totalSelisih'], (int) $helmExcel[8]);
    }

    public function test_bisa_mengunduh_seluruh_jenis_bila_diminta(): void
    {
        $baris = $this->unduh('&semua=1');

        $this->assertArrayHasKey('Helm Open Face', $baris);
        $this->assertArrayHasKey('Toolset Matic', $baris);
    }

    public function test_tanpa_plan_audit_id_ditolak(): void
    {
        $this->get('/api/audit-detail/perlengkapan/export-selisih')->assertStatus(422);
    }
}
