<?php

namespace Tests\Feature;

use App\Models\DbAhmOil;
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
 * yang selisihnya saja tanpa menyaring manual dari ribuan baris, DAN tanpa
 * memisah sendiri mana yang AHM Oil vs sparepart biasa. Selisih dihitung
 * ULANG di server dari field mentahnya (fisik, wo, saldoAkhir), bukan
 * dipercaya dari kolom "selisih" yang mungkin sudah tidak sinkron. Hasilnya
 * berupa 2 sheet: sheet 0 = AHM OIL'S, sheet 1 = SPAREPART — pengelompokan
 * memakai kode yang sama dengan rekap selisih di Report Audit PDF
 * (ReportPdfController::splitOilSparepart), supaya keduanya konsisten.
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

    /** @return array{0: string, 1: string} [teksSheetOil, teksSheetSparepart] */
    private function teksTiapSheet(string $xlsxContent): array
    {
        $path = tempnam(sys_get_temp_dir(), 'hgp_selisih_') . '.xlsx';
        file_put_contents($path, $xlsxContent);
        $spreadsheet = IOFactory::load($path);
        $join = fn($sheet) => implode(' ', array_map(
            fn($r) => implode(' ', array_map('strval', $r)),
            $sheet->toArray(null, true, true, false)
        ));
        $teks = [$join($spreadsheet->getSheet(0)), $join($spreadsheet->getSheet(1))];
        unlink($path);
        return $teks;
    }

    private function export(): array
    {
        $res = $this->get("/api/audit-detail/hgp/export-selisih?plan_audit_id={$this->plan->id}")->assertOk();
        return $this->teksTiapSheet($res->streamedContent());
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

        [$oil, $sparepart] = $this->export();

        $this->assertStringContainsString('31500KZR602', $sparepart);
        $this->assertStringNotContainsString('99999XXX', $sparepart);
        $this->assertStringNotContainsString('99999XXX', $oil);
        $this->assertStringContainsString('0433/28/07/2026/SPT-IAT', $sparepart);
        $this->assertStringContainsString('Sahril Mahendra & Rian Alfian', $sparepart);
    }

    public function test_item_terdaftar_di_db_ahm_oil_masuk_sheet_oil_sisanya_sheet_sparepart(): void
    {
        DbAhmOil::create(['kode' => '08232M99K8LN0', 'nama' => 'SCOOTER GEAR OIL']);

        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => '08232M99K8LN0', 'sparepart' => 'SCOOTER GEAR OIL (120ML)REP', 'saldoAkhir' => 349, 'fisik' => 347, 'wo' => 0, 'hargaHet' => 16500],
                ['noPart' => '31500KZR602', 'sparepart' => 'BATTERY(GTZ6V)', 'saldoAkhir' => 8, 'fisik' => 7, 'wo' => 0, 'hargaHet' => 302000],
            ],
        ]);

        [$oil, $sparepart] = $this->export();

        $this->assertStringContainsString('SCOOTER GEAR OIL', $oil);
        $this->assertStringNotContainsString('BATTERY', $oil);
        $this->assertStringContainsString('BATTERY', $sparepart);
        $this->assertStringNotContainsString('SCOOTER GEAR OIL', $sparepart);
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

        [, $sparepart] = $this->export();

        $this->assertStringContainsString('STALE SELISIH', $sparepart);
        $this->assertStringNotContainsString('STALE PUNYA SELISIH PALSU', $sparepart);
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

        [, $sparepart] = $this->export();

        $this->assertStringNotContainsString('WO MENUTUP SELISIH', $sparepart);
    }

    public function test_tanpa_data_hgp_tetap_menghasilkan_file_kosong_bukan_error(): void
    {
        [$oil, $sparepart] = $this->export();
        $this->assertStringContainsString('SPT', $oil);
        $this->assertStringContainsString('SPT', $sparepart);
    }

    public function test_plan_audit_id_wajib_diisi(): void
    {
        $this->get('/api/audit-detail/hgp/export-selisih')->assertStatus(422);
    }
}
