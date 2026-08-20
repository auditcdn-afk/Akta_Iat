<?php

namespace Tests\Feature;

use App\Models\PemeriksaanMaterai;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Keterangan per baris transaksi Materai hasil import HTML MTP SPP boleh
 * ditulis/diedit manual oleh auditor, dan tidak boleh hilang kalau file
 * HTML yang sama di-re-import (lihat PemeriksaanMateraiController::mergeKeterangan).
 */
class PemeriksaanMateraiControllerTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0001/01/01/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);

        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'materai',
            'nama_auditee'  => 'Auditee Test',
        ])->assertOk();
    }

    private function html(string $keterangan = ''): string
    {
        return <<<HTML
            <table>
              <tr><th>METERAI RP 10.000</th></tr>
              <tr><td>SALDO AWAL</td><td></td><td></td><td></td><td></td><td>10</td></tr>
              <tr><td>1</td><td>01-01-2026</td><td>NM001</td><td>{$keterangan}</td><td>0</td><td>1</td><td>9</td></tr>
            </table>
            HTML;
    }

    private function upload(string $keterangan = ''): \Illuminate\Testing\TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('spp.htm', $this->html($keterangan));

        return $this->postJson('/api/audit-detail/materai/upload', [
            'plan_audit_id' => $this->plan->id,
            'file'          => $file,
        ]);
    }

    public function test_upload_parse_transaksi_dari_html(): void
    {
        $response = $this->upload();

        $response->assertOk();
        $this->assertSame('01-01-2026', $response->json('data.0.transaksi.0.tanggal'));
        $this->assertSame('NM001', $response->json('data.0.transaksi.0.nomor'));
    }

    public function test_update_keterangan_transaksi_tersimpan(): void
    {
        $this->upload();
        $rec = PemeriksaanMaterai::first();

        $response = $this->putJson("/api/audit-detail/materai/{$rec->id}/transaksi-keterangan", [
            'index'      => 0,
            'keterangan' => 'Catatan manual dari auditor',
        ]);

        $response->assertOk();
        $this->assertSame('Catatan manual dari auditor', $response->json('data.transaksi.0.keterangan'));
        $this->assertSame('Catatan manual dari auditor', $rec->fresh()->transaksi_json[0]['keterangan']);
    }

    public function test_keterangan_manual_tidak_hilang_saat_html_direimport(): void
    {
        $this->upload(); // keterangan asli dari HTML kosong
        $rec = PemeriksaanMaterai::first();

        $this->putJson("/api/audit-detail/materai/{$rec->id}/transaksi-keterangan", [
            'index'      => 0,
            'keterangan' => 'Sudah dicek fisik, sesuai',
        ])->assertOk();

        // Re-import file HTML yang sama (mis. auditor refresh data saldo)
        $response = $this->upload();

        $response->assertOk();
        $this->assertSame('Sudah dicek fisik, sesuai', $response->json('data.0.transaksi.0.keterangan'));
        $this->assertSame(
            'Sudah dicek fisik, sesuai',
            PemeriksaanMaterai::first()->transaksi_json[0]['keterangan']
        );
    }

    public function test_reimport_dengan_keterangan_baru_dari_html_tidak_ditimpa_kosong(): void
    {
        $this->upload('Keterangan dari sistem');
        $rec = PemeriksaanMaterai::first();
        $this->assertSame('Keterangan dari sistem', $rec->transaksi_json[0]['keterangan']);

        $this->putJson("/api/audit-detail/materai/{$rec->id}/transaksi-keterangan", [
            'index'      => 0,
            'keterangan' => 'Diedit auditor',
        ])->assertOk();

        // Re-import: baris HTML kali ini kosong lagi (skenario umum: kolom
        // keterangan di sistem sumber memang sering kosong) — edit manual
        // auditor harus tetap dipertahankan, bukan ikut kosong lagi.
        $response = $this->upload();

        $this->assertSame('Diedit auditor', $response->json('data.0.transaksi.0.keterangan'));
    }

    public function test_index_di_luar_jangkauan_dapat_404(): void
    {
        $this->upload();
        $rec = PemeriksaanMaterai::first();

        $this->putJson("/api/audit-detail/materai/{$rec->id}/transaksi-keterangan", [
            'index'      => 5,
            'keterangan' => 'x',
        ])->assertStatus(404);
    }
}
