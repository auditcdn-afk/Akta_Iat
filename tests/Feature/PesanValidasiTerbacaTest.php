<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pesan yang sampai ke auditor harus berupa kalimat, bukan kunci terjemahan.
 *
 * Dilaporkan: menekan Simpan di tab Grading memunculkan tulisan "validation.in"
 * begitu saja. Dua hal yang membuatnya begitu:
 *
 *   1. Widget Nama Auditor/Auditee ikut ditampilkan di tab Grading, padahal
 *      Grading bukan tool pemeriksaan dan tidak punya pasangan auditor/auditee.
 *      Kirimannya ditolak server -- memang seharusnya begitu.
 *   2. Laravel tidak punya berkas terjemahan sama sekali di aplikasi ini, jadi
 *      pesan penolakannya keluar sebagai kunci mentah "validation.in".
 */
class PesanValidasiTerbacaTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seperti di hosting: aplikasinya berbahasa Indonesia, dan cadangan
        // bahasanya pun "id". Dengan setelan ini pesan bawaan Laravel (yang
        // hanya tersedia dalam bahasa Inggris) tidak lagi menolong -- kalau
        // berkas lang/id tidak lengkap, yang muncul kunci mentahnya.
        config(['app.locale' => 'id', 'app.fallback_locale' => 'id']);
        $this->app->setLocale('id');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0460/01/09/2026/SPT-IAT', 'cabang' => 'CSC UJT',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);
    }

    /** Tidak satu pun pesan penolakan boleh berbentuk "validation.sesuatu". */
    public function test_tool_yang_bukan_tab_pemeriksaan_ditolak_dengan_kalimat(): void
    {
        $res = $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'grading',
            'nama_auditee'  => 'Baccan',
        ])->assertStatus(422);

        $pesan = (string) $res->json('message');

        $this->assertStringNotContainsString('validation.', $pesan,
            'Auditor tidak boleh melihat kunci terjemahan mentah seperti "validation.in".');
        $this->assertNotEmpty($pesan);
    }

    /** Berlaku untuk semua bentuk penolakan, bukan cuma aturan "in". */
    public function test_isian_wajib_yang_kosong_juga_berpesan_kalimat(): void
    {
        $res = $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
        ])->assertStatus(422);

        $this->assertStringNotContainsString('validation.', (string) $res->json('message'));
    }

    /** Plan yang tidak ada pun harus berpesan kalimat, bukan "validation.exists". */
    public function test_plan_tidak_ada_berpesan_kalimat(): void
    {
        $res = $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => 999999,
            'tool'          => 'kas',
            'nama_auditee'  => 'Baccan',
        ])->assertStatus(422);

        $this->assertStringNotContainsString('validation.', (string) $res->json('message'));
    }

    /** Seluruh tab pemeriksaan yang sah tetap diterima. */
    public function test_semua_tab_pemeriksaan_yang_sah_diterima(): void
    {
        foreach (array_keys(config('audit_tabs')) as $tool) {
            $this->postJson('/api/audit-detail/auditor', [
                'plan_audit_id' => $this->plan->id,
                'tool'          => $tool,
                'nama_auditee'  => 'Baccan',
            ])->assertSuccessful();
        }
    }

    /** Pesannya bukan sekadar bukan-kunci, tapi memang berbahasa Indonesia. */
    public function test_pesan_penolakan_berbahasa_indonesia(): void
    {
        $res = $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
        ])->assertStatus(422);

        $this->assertStringContainsString('wajib diisi', (string) $res->json('message'));
        $this->assertStringContainsString('Nama Auditee', (string) $res->json('message'));
    }

    /**
     * Seluruh kunci bawaan Laravel harus ada terjemahannya. Satu saja yang
     * terlewat akan muncul sebagai kunci mentah di layar auditor, dan baru
     * ketahuan saat aturan itu kebetulan dilanggar seseorang.
     */
    public function test_tidak_ada_kunci_terjemahan_yang_terlewat(): void
    {
        $bawaan = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $kita   = require lang_path('id/validation.php');

        $terlewat = array_diff(array_keys($bawaan), array_keys($kita));

        $this->assertSame([], array_values($terlewat),
            'Kunci berikut belum diterjemahkan: ' . implode(', ', $terlewat));
    }
}
