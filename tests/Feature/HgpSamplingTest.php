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
 * Sampling Audit (30/50 item). Khusus untuk plan berjenis audit
 * "Audit Online Kas + HGP & AHM Oils", tool HGP biasa ini juga harus
 * mengambil sample acak 30 item — lihat HgpController::shouldSample().
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

    public function test_jenis_audit_mirip_tapi_bukan_persis_tidak_ikut_disampling(): void
    {
        // "Audit Kas + HGP & AHM Oils" (tanpa kata "Online") adalah opsi dropdown
        // yang BERBEDA — sengaja tidak boleh ikut ke-match.
        $plan = PlanAudit::query()->create([
            'no_spt' => '0003/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Kas + HGP & AHM Oils', 'status' => 'running',
        ]);

        $response = $this->post('/api/audit-detail/hgp/parse-excel', [
            'plan_audit_id' => $plan->id,
            'file'          => $this->buildHgpExcel(80),
        ]);

        $response->assertOk();
        $this->assertCount(80, $response->json('data'));
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
