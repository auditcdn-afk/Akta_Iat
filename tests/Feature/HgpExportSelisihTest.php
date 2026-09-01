<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Tombol "Export Selisih" di tab HGP & AHM Oils — auditor ingin menarik item
 * yang selisihnya saja tanpa menyaring manual dari ribuan baris. Selisih
 * dihitung ULANG di server dari field mentahnya (fisik, wo, saldoAkhir),
 * bukan dipercaya dari kolom "selisih" yang mungkin sudah tidak sinkron.
 */
class HgpExportSelisihTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0433/28/07/2026/SPT-IAT', 'cabang' => 'CSC PRW',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
            'kepala_tim' => 'Abdul Aziz', 'tim' => ['Abdul Aziz'],
        ]);

        PemeriksaanAuditor::create([
            'plan_audit_id' => $this->plan->id,
            'tool' => 'hgp',
            'nama_auditor' => 'Abdul Aziz',
            'nama_auditee' => 'Sahril Mahendra & Rian Alfian',
        ]);
    }

    private function sheetRows(string $xlsxContent): array
    {
        $path = tempnam(sys_get_temp_dir(), 'hgp_selisih_') . '.xlsx';
        file_put_contents($path, $xlsxContent);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        unlink($path);
        return $rows;
    }

    public function test_hanya_item_dengan_selisih_yang_diekspor(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => '31500KZR602', 'sparepart' => 'BATTERY(GTZ6V)', 'saldoAkhir' => 8, 'fisik' => 7, 'wo' => 0, 'selisih' => -1, 'hargaHet' => 302000],
                ['noPart' => '99999XXX', 'sparepart' => 'TIDAK ADA SELISIH', 'saldoAkhir' => 5, 'fisik' => 5, 'wo' => 0, 'selisih' => 0, 'hargaHet' => 1000],
            ],
        ]);

        $res = $this->get("/api/audit-detail/hgp/export-selisih?plan_audit_id={$this->plan->id}")->assertOk();
        $rows = $this->sheetRows($res->streamedContent());

        $joined = implode(' ', array_map(fn($r) => implode(' ', array_map('strval', $r)), $rows));
        $this->assertStringContainsString('31500KZR602', $joined);
        $this->assertStringNotContainsString('99999XXX', $joined);
        $this->assertStringContainsString('0433/28/07/2026/SPT-IAT', $joined);
        $this->assertStringContainsString('Sahril Mahendra & Rian Alfian', $joined);
    }

    public function test_selisih_dihitung_ulang_bukan_dipercaya_dari_field_tersimpan(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                // Field "selisih" tersimpan sengaja salah (0, padahal fisik+wo != saldo) —
                // exportnya harus tetap menghitung ulang dan tetap memasukkan baris ini.
                ['noPart' => 'X1', 'sparepart' => 'STALE SELISIH', 'saldoAkhir' => 10, 'fisik' => 8, 'wo' => 0, 'selisih' => 0, 'hargaHet' => 1000],
                // Sebaliknya: field tersimpan bilang ada selisih padahal fisik+wo == saldo —
                // baris ini TIDAK boleh ikut karena hitungan ulangnya nol.
                ['noPart' => 'X2', 'sparepart' => 'STALE PUNYA SELISIH PALSU', 'saldoAkhir' => 10, 'fisik' => 10, 'wo' => 0, 'selisih' => -99, 'hargaHet' => 1000],
            ],
        ]);

        $res = $this->get("/api/audit-detail/hgp/export-selisih?plan_audit_id={$this->plan->id}")->assertOk();
        $rows = $this->sheetRows($res->streamedContent());
        $joined = implode(' ', array_map(fn($r) => implode(' ', array_map('strval', $r)), $rows));

        $this->assertStringContainsString('STALE SELISIH', $joined);
        $this->assertStringNotContainsString('STALE PUNYA SELISIH PALSU', $joined);
    }

    public function test_wo_ikut_dihitung_sebagai_penambah_fisik(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                // fisik(5) + wo(5) == saldo(10) -> selisih 0, tidak boleh ikut.
                ['noPart' => 'Y1', 'sparepart' => 'WO MENUTUP SELISIH', 'saldoAkhir' => 10, 'fisik' => 5, 'wo' => 5, 'hargaHet' => 1000],
            ],
        ]);

        $res = $this->get("/api/audit-detail/hgp/export-selisih?plan_audit_id={$this->plan->id}")->assertOk();
        $rows = $this->sheetRows($res->streamedContent());
        $joined = implode(' ', array_map(fn($r) => implode(' ', array_map('strval', $r)), $rows));

        $this->assertStringNotContainsString('WO MENUTUP SELISIH', $joined);
    }

    public function test_tanpa_data_hgp_tetap_menghasilkan_file_kosong_bukan_error(): void
    {
        $res = $this->get("/api/audit-detail/hgp/export-selisih?plan_audit_id={$this->plan->id}")->assertOk();
        $rows = $this->sheetRows($res->streamedContent());
        $this->assertNotEmpty($rows); // minimal header info + baris judul kolom
    }

    public function test_plan_audit_id_wajib_diisi(): void
    {
        $this->get('/api/audit-detail/hgp/export-selisih')->assertStatus(422);
    }
}
