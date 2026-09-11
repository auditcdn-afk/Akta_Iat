<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanPerlengkapan;
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

    /** Seluruh baris sheet yang punya label di kolom B, termasuk baris TOTAL. */
    private function barisSheet(string $query = ''): array
    {
        $res = $this->get('/api/audit-detail/perlengkapan/export-selisih?plan_audit_id=' . $this->plan->id . $query);
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $res->streamedContent());
        $rows = IOFactory::load($tmp)->getActiveSheet()->toArray();
        @unlink($tmp);

        $hasil = [];
        foreach ($rows as $row) {
            if (! empty($row[1])) $hasil[$row[1]] = $row;
        }

        return $hasil;
    }

    /**
     * Data yang mencakup semua bentuk selisih sekaligus:
     *  - Helm Open Face : kurang (2 butuh, 1 ketemu di unit)
     *  - Toolset Matic  : pas    (2 butuh, 2 ketemu di unit)
     *  - Baterai 3 Ah   : lebih  (tidak ada di db_perlengkapan, hanya Luar SMH)
     */
    private function dataBermacamSelisih(): void
    {
        PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Baterai 3 Ah',
            'fisik'              => 3,
            'saldo'              => 0,
            'selisih'            => 3,
            'penjelasan'         => 'Stok gudang lebih',
        ]);

        // Helm yang kurang ditutup sebagian dari gudang.
        PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Helm Open Face',
            'fisik'              => 1,
            'saldo'              => 1,
            'selisih'            => 0,
            'penjelasan'         => 'Ditemukan di gudang',
        ]);
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

    /** Baris rekap gabungan yang dipakai halaman Report Audit. */
    private function rekapReportAudit(): array
    {
        return $this->get(route('akta.report-audit.pdf', $this->plan))
            ->assertOk()->original->getData()['rekapGabungan'] ?? [];
    }

    /**
     * Jaminan inti: SETIAP baris dan SETIAP kolom di Excel sama dengan Report
     * Audit — bukan hanya satu jenis yang kebetulan dicek. Dijalankan pada data
     * yang mencakup semua bentuk selisih: kurang, pas, lebih, dan jenis yang
     * hanya ada di Luar SMH.
     */
    public function test_seluruh_baris_dan_kolom_sama_dengan_report_audit(): void
    {
        $this->dataBermacamSelisih();

        $excel  = $this->unduh('&semua=1');
        $report = $this->rekapReportAudit();

        $this->assertNotEmpty($report, 'Report Audit harus punya baris rekap gabungan.');
        $this->assertSame(
            count($report),
            count($excel),
            'Jumlah jenis di Excel harus sama dengan di Report Audit.'
        );

        foreach ($report as $r) {
            $jenis = $r['jenis'];
            $this->assertArrayHasKey($jenis, $excel, "Jenis {$jenis} ada di laporan tapi tidak di Excel.");

            $baris = $excel[$jenis];
            $this->assertSame((int) $r['smhSaldo'],     (int) $baris[2], "SMH Saldo {$jenis}");
            $this->assertSame((int) $r['smhFisik'],     (int) $baris[3], "SMH Fisik {$jenis}");
            $this->assertSame((int) $r['smhSelisih'],   (int) $baris[4], "SMH Selisih {$jenis}");
            $this->assertSame((int) $r['luarSaldo'],    (int) $baris[5], "Luar Saldo {$jenis}");
            $this->assertSame((int) $r['luarFisik'],    (int) $baris[6], "Luar Fisik {$jenis}");
            $this->assertSame((int) $r['luarSelisih'],  (int) $baris[7], "Luar Selisih {$jenis}");
            $this->assertSame((int) $r['totalSelisih'], (int) $baris[8], "Total Selisih {$jenis}");
            $this->assertSame($r['keterangan'] ?: '-',  (string) $baris[9], "Keterangan {$jenis}");
        }
    }

    /**
     * Baris TOTAL juga harus cocok. Saat yang diunduh hanya jenis berselisih,
     * TOTAL baris yang tampil memang lebih kecil — karena itu sheet-nya ikut
     * menulis "TOTAL SELURUH JENIS" yang angkanya sama dengan laporan, supaya
     * tidak ada angka yang terlihat bertentangan.
     */
    public function test_baris_total_seluruh_jenis_sama_dengan_report_audit(): void
    {
        $this->dataBermacamSelisih();

        $report = $this->rekapReportAudit();
        $totalReport = [
            'smhSaldo'     => array_sum(array_column($report, 'smhSaldo')),
            'smhFisik'     => array_sum(array_column($report, 'smhFisik')),
            'luarSaldo'    => array_sum(array_column($report, 'luarSaldo')),
            'luarFisik'    => array_sum(array_column($report, 'luarFisik')),
            'totalSelisih' => array_sum(array_column($report, 'totalSelisih')),
        ];

        foreach (['' => 'TOTAL SELURUH JENIS (sama dengan Report Audit)', '&semua=1' => 'TOTAL'] as $query => $label) {
            $baris = $this->barisSheet($query)[$label] ?? null;
            $this->assertNotNull($baris, "Baris \"{$label}\" harus ada di sheet.");

            $this->assertSame($totalReport['smhSaldo'],     (int) $baris[2], "TOTAL SMH Saldo ({$label})");
            $this->assertSame($totalReport['smhFisik'],     (int) $baris[3], "TOTAL SMH Fisik ({$label})");
            $this->assertSame($totalReport['luarSaldo'],    (int) $baris[5], "TOTAL Luar Saldo ({$label})");
            $this->assertSame($totalReport['luarFisik'],    (int) $baris[6], "TOTAL Luar Fisik ({$label})");
            $this->assertSame($totalReport['totalSelisih'], (int) $baris[8], "TOTAL Selisih ({$label})");
        }
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
