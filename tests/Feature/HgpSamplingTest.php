<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tool "HGP & AHM Oils" biasanya memuat SEMUA item hasil parsing Excel — beda
 * dari tool terpisah "RSA HGP & AHM Oils" yang memang didesain sebagai Random
 * Sampling Audit (30/50 item).
 *
 * Dua jenis audit adalah pengecualian, dengan aturan yang BERBEDA:
 *
 *   Audit Online Kas + HGP & AHM Oils : 30 item acak saja.
 *   Audit Kas + HGP & AHM Oils        : SELURUH item yang ada di database
 *                                       AHM Oils, ditambah 30 part lain acak.
 *
 * Lihat HgpController::aturanSample().
 */
class HgpSamplingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor1',
            'role'     => 'auditor',
        ]));
    }

    /** Bikin file .xlsx dengan format kolom yang dikenali HgpController::parseExcel(). */
    private function buildHgpExcel(int $rowCount): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();

        // Header: col0=NO, col1=NO PART, col2=NAMA PART, col5=AWAL, col9=AKHIR, col10=KETERANGAN
        $ws->fromArray(['NO', 'NO PART', 'NAMA PART', '', '', 'AWAL', 'MASUK', 'KELUAR', 'ADJUST', 'AKHIR', 'KETERANGAN'], null, 'A1');

        for ($i = 1; $i <= $rowCount; $i++) {
            $ws->fromArray([$i, "PART-{$i}", "Sparepart {$i}", '', '', 10, 0, 0, 0, 10, 'Gudang A'], null, 'A' . ($i + 1));
        }

        $path = tempnam(sys_get_temp_dir(), 'hgp') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        return new UploadedFile($path, 'onhand.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** Berkas dengan daftar No. Part yang ditentukan sendiri. */
    private function buildHgpExcelDenganPart(array $noParts): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();

        $ws->fromArray(['NO', 'NO PART', 'NAMA PART', '', '', 'AWAL', 'MASUK', 'KELUAR', 'ADJUST', 'AKHIR', 'KETERANGAN'], null, 'A1');

        foreach (array_values($noParts) as $i => $noPart) {
            $ws->fromArray([$i + 1, $noPart, "Nama {$noPart}", '', '', 10, 0, 0, 0, 10, 'Gudang A'], null, 'A' . ($i + 2));
        }

        $path = tempnam(sys_get_temp_dir(), 'hgp') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        return new UploadedFile($path, 'onhand.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** Jenis yang memuat 30 item acak SAJA, tanpa daftar AHM Oils. */
    private function planOnlineKas(string $noSpt): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt' => $noSpt, 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
        ]);
    }

    /** Jenis yang memuat SELURUH item AHM Oils + 30 part lain. */
    private function planKas(string $noSpt): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt' => $noSpt, 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Kas + HGP & AHM Oils', 'status' => 'running',
        ]);
    }

    /** @param string[] $kode */
    private function isiMasterAhmOil(array $kode): void
    {
        foreach ($kode as $k) {
            \App\Models\DbAhmOil::query()->create(['kode' => $k, 'nama' => "Oli {$k}"]);
        }
    }

    public function test_seluruh_item_ahm_oils_ikut_diperiksa_plus_30_part_lain(): void
    {
        $plan = $this->planKas('0010/01/01/2026/SPT-IAT');

        // 12 oli di master, tersebar di antara 988 sparepart lain.
        $oli = [];
        for ($i = 1; $i <= 12; $i++) {
            $oli[] = "OLI-{$i}";
        }
        $this->isiMasterAhmOil($oli);

        $part = [];
        for ($i = 1; $i <= 988; $i++) {
            $part[] = "PART-{$i}";
        }

        // Oli disisipkan di tengah-tengah, bukan berurutan di depan.
        $daftar = $part;
        foreach ($oli as $n => $kode) {
            array_splice($daftar, ($n + 1) * 70, 0, [$kode]);
        }

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        $this->assertSame(1000, $res->json('totalFound'));
        $this->assertSame(12, $res->json('ahmOil'));
        $this->assertCount(42, $res->json('data'));   // 12 oli + 30 part lain
        $this->assertTrue($res->json('sampled'));

        // Keduabelas oli HARUS ada, satu pun tidak boleh tertinggal.
        $terpilih = array_column($res->json('data'), 'noPart');
        foreach ($oli as $kode) {
            $this->assertContains($kode, $terpilih, "Item AHM Oils {$kode} tidak ikut diperiksa.");
        }
    }

    public function test_urutan_item_mengikuti_urutan_berkas(): void
    {
        $plan = $this->planKas('0011/01/01/2026/SPT-IAT');
        $this->isiMasterAhmOil(['OLI-A', 'OLI-B']);

        $daftar = [];
        for ($i = 1; $i <= 100; $i++) {
            $daftar[] = "PART-{$i}";
        }
        array_splice($daftar, 50, 0, ['OLI-A']);
        array_splice($daftar, 80, 0, ['OLI-B']);

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        $terpilih = array_column($res->json('data'), 'noPart');
        $urutanBerkas = array_values(array_filter($daftar, fn($p) => in_array($p, $terpilih, true)));

        $this->assertSame($urutanBerkas, $terpilih);
    }

    public function test_master_ahm_oils_kosong_diberi_tahu_ke_auditor(): void
    {
        $plan = $this->planKas('0012/01/01/2026/SPT-IAT');

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(80),
        ])->assertOk();

        $this->assertSame(0, $res->json('ahmOil'));
        $this->assertCount(30, $res->json('data'));
        $this->assertStringContainsString('database AHM Oils', (string) $res->json('catatan'));
    }

    public function test_seluruh_oli_ikut_walau_jumlahnya_melebihi_ukuran_sample(): void
    {
        $plan = $this->planKas('0013/01/01/2026/SPT-IAT');

        $oli = [];
        for ($i = 1; $i <= 45; $i++) {
            $oli[] = "OLI-{$i}";
        }
        $this->isiMasterAhmOil($oli);

        $daftar = $oli;
        for ($i = 1; $i <= 100; $i++) {
            $daftar[] = "PART-{$i}";
        }

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        // 45 oli tetap seluruhnya, ditambah 30 part lain.
        $this->assertSame(45, $res->json('ahmOil'));
        $this->assertCount(75, $res->json('data'));
    }

    public function test_part_lain_kurang_dari_30_ikut_semua_tanpa_ada_yang_dibuang(): void
    {
        $plan = $this->planKas('0014/01/01/2026/SPT-IAT');
        $this->isiMasterAhmOil(['OLI-A', 'OLI-B']);

        $daftar = ['OLI-A', 'PART-1', 'PART-2', 'OLI-B'];

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        $this->assertCount(4, $res->json('data'));
        $this->assertFalse($res->json('sampled'));
        $this->assertSame(2, $res->json('ahmOil'));
    }

    public function test_pencocokan_oli_tidak_memandang_besar_kecil_huruf_dan_spasi(): void
    {
        $plan = $this->planKas('0015/01/01/2026/SPT-IAT');
        $this->isiMasterAhmOil([' 08232M99K0LN5 ', 'mpx1-10w30']);

        $daftar = ['08232M99K0LN5', 'MPX1-10W30'];
        for ($i = 1; $i <= 60; $i++) {
            $daftar[] = "PART-{$i}";
        }

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        $this->assertSame(2, $res->json('ahmOil'));

        $terpilih = array_column($res->json('data'), 'noPart');
        $this->assertContains('08232M99K0LN5', $terpilih);
        $this->assertContains('MPX1-10W30', $terpilih);
    }

    public function test_jenis_audit_lain_tidak_terpengaruh_master_ahm_oils(): void
    {
        $this->isiMasterAhmOil(['OLI-A']);

        $plan = PlanAudit::query()->create([
            'no_spt' => '0016/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(80),
        ])->assertOk();

        $this->assertCount(80, $res->json('data'));
        $this->assertNull($res->json('ahmOil'));
    }

    public function test_disampling_30_item_untuk_jenis_audit_online_kas_hgp(): void
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
        ]);

        $response = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(80),
        ]);

        $response->assertOk();
        $this->assertCount(30, $response->json('data'));
        $this->assertSame(80, $response->json('totalFound'));
        $this->assertSame(30, $response->json('sampleSize'));
        $this->assertTrue($response->json('sampled'));
    }

    public function test_jenis_audit_lain_tetap_memuat_semua_item_tanpa_sampling(): void
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        $response = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(80),
        ]);

        $response->assertOk();
        $this->assertCount(80, $response->json('data'));
        $this->assertNull($response->json('sampled'));
        $this->assertNull($response->json('totalFound'));
    }

    public function test_audit_online_kas_tidak_ikut_memuat_daftar_ahm_oils(): void
    {
        // Dua opsi dropdown yang berbeda, dan aturannya memang berbeda:
        // "Audit Online Kas + HGP & AHM Oils" cukup 30 item acak saja.
        $this->isiMasterAhmOil(['OLI-1', 'OLI-2', 'OLI-3']);

        $plan = $this->planOnlineKas('0003/01/01/2026/SPT-IAT');

        $daftar = ['OLI-1', 'OLI-2', 'OLI-3'];
        for ($i = 1; $i <= 200; $i++) {
            $daftar[] = "PART-{$i}";
        }

        $res = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcelDenganPart($daftar),
        ])->assertOk();

        // Tetap 30 -- bukan 33 -- dan tidak ada hitungan oli pada jawabannya.
        $this->assertCount(30, $res->json('data'));
        $this->assertNull($res->json('ahmOil'));
        $this->assertNull($res->json('catatan'));
    }

    public function test_tidak_disampling_kalau_item_ditemukan_kurang_dari_atau_sama_dengan_30(): void
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0004/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
        ]);

        $response = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(20),
        ]);

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
        $this->assertFalse($response->json('sampled'));
    }

    public function test_tanpa_plan_audit_id_tidak_disampling(): void
    {
        $response = $this->post('/api/audit-detail/hgp/parse-excel', [
            'file' => $this->buildHgpExcel(80),
        ]);

        $response->assertOk();
        $this->assertCount(80, $response->json('data'));
    }
}
