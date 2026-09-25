<?php

namespace Tests\Feature;

use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lampiran rekomendasi audit.
 *
 * Form Rekomendasi sudah lama punya "Upload File Lampiran", tapi berkasnya
 * tidak pernah tersimpan: layar hanya mengambil NAMANYA lalu menempelkannya
 * sebagai teks di akhir deskripsi ("Lampiran: REKAP SELISIH.pdf"). Berkasnya
 * sendiri dibuang -- itulah sebabnya lampirannya tidak pernah muncul.
 */
class RekomendasiLampiranTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0461/01/09/2026/SPT-IAT',
            'cabang'      => 'WHS Unit ARK',
            'jenis_audit' => 'Audit Warehouse PART',
            'status'      => 'running',
        ]);
    }

    private function rekomendasi(): AuditRecommendation
    {
        return AuditRecommendation::query()->create([
            'plan_audit_id' => $this->plan->id,
            'judul'         => 'Selisih HGP & AHM Oil',
            'status'        => 'open',
            'prioritas'     => 'sedang',
            'created_by'    => 'auditor1',
        ]);
    }

    private function unggah(AuditRecommendation $rek, ?UploadedFile $berkas = null)
    {
        // Accept: application/json seperti yang dikirim authHeaders() di layar --
        // tanpa itu Laravel membalas pengalihan (302), bukan 422 yang terbaca.
        return $this->post("/api/recommendations/{$rek->id}/lampiran", [
            'lampiran' => $berkas ?? UploadedFile::fake()->create('REKAP SELISIH.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    public function test_lampiran_tersimpan_dan_bisa_dibuka(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));

        $res = $this->unggah($rek)->assertOk();

        $rek->refresh();
        $this->assertNotNull($rek->lampiran_path, 'berkasnya harus benar-benar tersimpan');
        Storage::disk('public')->assertExists($rek->lampiran_path);

        // Nama aslinya dipakai supaya yang dibaca orang bukan nama acak.
        $this->assertSame('REKAP SELISIH.pdf', $rek->lampiran_nama);
        $this->assertSame('REKAP SELISIH.pdf', $res->json('data.lampiranNama'));
        $this->assertStringContainsString($rek->lampiran_path, (string) $res->json('data.lampiranUrl'));
    }

    public function test_daftar_membawa_tautan_lampiran(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));
        $this->unggah($rek)->assertOk();

        $baris = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()->json('data.0');

        $this->assertSame('REKAP SELISIH.pdf', $baris['lampiranNama']);
        $this->assertNotNull($baris['lampiranUrl']);
    }

    public function test_rekomendasi_tanpa_lampiran_tidak_membawa_tautan(): void
    {
        $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));

        $baris = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()->json('data.0');

        $this->assertNull($baris['lampiranUrl']);
        $this->assertNull($baris['lampiranNama']);
    }

    public function test_mengunggah_ulang_mengganti_dan_membuang_berkas_lama(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));

        $this->unggah($rek)->assertOk();
        $lama = $rek->fresh()->lampiran_path;

        $this->unggah($rek, UploadedFile::fake()->create('REKAP BARU.pdf', 90, 'application/pdf'))->assertOk();
        $baru = $rek->fresh()->lampiran_path;

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
        $this->assertSame('REKAP BARU.pdf', $rek->fresh()->lampiran_nama);
    }

    public function test_lampiran_bisa_dihapus(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));
        $this->unggah($rek)->assertOk();
        $path = $rek->fresh()->lampiran_path;

        $this->deleteJson("/api/recommendations/{$rek->id}/lampiran")->assertOk();

        $this->assertNull($rek->fresh()->lampiran_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_berkas_ikut_terbuang_saat_rekomendasinya_dihapus(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));
        $this->unggah($rek)->assertOk();
        $path = $rek->fresh()->lampiran_path;

        $this->deleteJson("/api/recommendations/{$rek->id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_jenis_berkas_dan_ukuran_dibatasi(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'AUDIT']));

        $this->unggah($rek, UploadedFile::fake()->create('virus.exe', 10))->assertStatus(422);
        $this->unggah($rek, UploadedFile::fake()->create('besar.pdf', 11000, 'application/pdf'))->assertStatus(422);

        $this->assertNull($rek->fresh()->lampiran_path);
    }

    public function test_hanya_admin_manajer_auditor_yang_boleh_melampirkan(): void
    {
        $rek = $this->rekomendasi();

        foreach ([['admin', 200], ['manajer', 200], ['auditor', 200], ['whs', 403], ['viewer', 403]] as [$role, $harapan]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => 'WHS Unit ARK']));
            $this->unggah($rek)->assertStatus($harapan);
        }
    }
}
