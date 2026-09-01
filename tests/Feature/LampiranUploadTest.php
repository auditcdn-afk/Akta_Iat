<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanLampiran;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tab Lampiran Audit tadinya cuma menerima PDF/JPG/PNG/DOC/DOCX — auditor
 * perlu melampirkan file Excel juga (mis. data pendukung mentah), jadi
 * XLS/XLSX ditambahkan ke daftar format yang diterima.
 */
class LampiranUploadTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/TEST/SPT-IAT', 'cabang' => 'CSC TEST',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
            'kepala_tim' => 'Abdul Aziz', 'tim' => ['Abdul Aziz'],
        ]);

        PemeriksaanAuditor::create([
            'plan_audit_id' => $this->plan->id,
            'tool' => 'lampiran',
            'nama_auditor' => 'Abdul Aziz',
            'nama_auditee' => 'Auditee Test',
        ]);
    }

    public function test_file_xlsx_bisa_diupload(): void
    {
        $file = UploadedFile::fake()->create('data-pendukung.xlsx', 500);

        $res = $this->post('/api/audit-detail/lampiran/upload', [
            'plan_audit_id' => $this->plan->id,
            'file' => $file,
        ])->assertOk();

        $data = $res->json('data');
        $this->assertCount(1, $data['files']);
        $this->assertSame('xlsx', $data['files'][0]['ext']);
    }

    public function test_file_xls_bisa_diupload(): void
    {
        $file = UploadedFile::fake()->create('data-lama.xls', 300);

        $res = $this->post('/api/audit-detail/lampiran/upload', [
            'plan_audit_id' => $this->plan->id,
            'file' => $file,
        ])->assertOk();

        $this->assertSame('xls', $res->json('data.files.0.ext'));
    }

    public function test_format_yang_masih_tidak_didukung_tetap_ditolak(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 100);

        $this->post('/api/audit-detail/lampiran/upload', [
            'plan_audit_id' => $this->plan->id,
            'file' => $file,
        ])->assertStatus(422);
    }

    public function test_gabung_pdf_melewati_file_excel_bukan_error(): void
    {
        PemeriksaanLampiran::create([
            'plan_audit_id' => $this->plan->id,
            'files_json' => [
                ['name' => 'data.xlsx', 'path' => 'lampiran/x/data.xlsx', 'ext' => 'xlsx', 'size' => 100, 'uploadedAt' => now()->toDateTimeString()],
            ],
        ]);

        $this->post('/api/audit-detail/lampiran/merge-pdf', [
            'plan_audit_id' => $this->plan->id,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tidak ada file PDF/gambar untuk digabung. File Word/Excel tidak bisa digabung otomatis.']);
    }
}
