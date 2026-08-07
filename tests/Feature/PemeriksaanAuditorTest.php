<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanKas;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nama Auditor & Nama Auditee — satu pasang per (plan_audit_id, tool).
 *
 * Nama Auditor selalu diturunkan di server dari user yang login (tidak pernah
 * dari kiriman klien); Nama Auditee wajib diisi manual sebelum data tool apa
 * pun (Kas, Bank, dst) bisa disimpan untuk plan tersebut — lihat
 * app/Http/Controllers/Concerns/RequiresAuditorAuditee.php.
 */
class PemeriksaanAuditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username'     => 'auditor1',
            'name'         => 'Auditor Satu',
            'display_name' => 'Auditor Satu',
            'role'         => 'auditor',
        ]);

        Sanctum::actingAs($this->user);

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0001/01/01/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);
    }

    public function test_tool_harus_dikenal_di_config_audit_tabs(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'tool-tidak-ada',
            'nama_auditee'  => 'Budi',
        ])->assertStatus(422);
    }

    public function test_nama_auditee_wajib_diisi(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
        ])->assertStatus(422)->assertJsonValidationErrors('nama_auditee');
    }

    public function test_nama_auditor_selalu_dari_server_bukan_dari_klien(): void
    {
        // Klien mengirim nama palsu untuk nama_auditor — server harus
        // mengabaikannya dan memakai display_name user yang sedang login.
        $response = $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditor'  => 'Nama Palsu Dari Klien',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ]);

        $response->assertOk();
        $this->assertSame('Auditor Satu', $response->json('data.namaAuditor'));

        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditor'  => 'Auditor Satu',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ]);
    }

    public function test_show_mengembalikan_default_nama_auditor_saat_belum_pernah_disimpan(): void
    {
        $response = $this->getJson(
            "/api/audit-detail/auditor?plan_audit_id={$this->plan->id}&tool=bank"
        );

        $response->assertOk();
        $this->assertNull($response->json('data.id'));
        $this->assertSame('Auditor Satu', $response->json('data.namaAuditor'));
        $this->assertNull($response->json('data.namaAuditee'));

        // Default tampilan ini belum tersimpan ke database.
        $this->assertDatabaseMissing('pemeriksaan_auditors', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'bank',
        ]);
    }

    public function test_upsert_berkunci_plan_dan_tool_tidak_bentrok_antar_plan_sama_tool(): void
    {
        $planLain = PlanAudit::query()->create([
            'no_spt'      => '0002/01/01/2026/SPT-IAT',
            'cabang'      => 'CSC ALB',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);

        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Plan Satu',
        ])->assertOk();

        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $planLain->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Plan Dua',
        ])->assertOk();

        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Plan Satu',
        ]);
        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $planLain->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Plan Dua',
        ]);
    }

    public function test_menyimpan_ulang_tool_yang_sama_memperbarui_baris_bukan_menambah(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Lama',
        ])->assertOk();

        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Baru',
        ])->assertOk();

        $this->assertSame(1, PemeriksaanAuditor::where('plan_audit_id', $this->plan->id)->where('tool', 'kas')->count());
        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Auditee Baru',
        ]);
    }

    // ── Guard di controller tool: representatif satu tool banyak-baris (Kas)
    //    dan satu tool JSON-blob (Cek Fisik) — lihat RequiresAuditorAuditee. ──

    public function test_simpan_kas_ditolak_sebelum_auditor_auditee_diisi(): void
    {
        $response = $this->postJson('/api/audit-detail/kas', [
            'plan_audit_id' => $this->plan->id,
            'nama_pos'      => 'Kas Besar',
            'saldo_fisik'   => 100000,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('pemeriksaan_kas', ['plan_audit_id' => $this->plan->id]);
    }

    public function test_simpan_kas_berhasil_setelah_auditor_auditee_diisi(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ])->assertOk();

        $response = $this->postJson('/api/audit-detail/kas', [
            'plan_audit_id' => $this->plan->id,
            'nama_pos'      => 'Kas Besar',
            'saldo_fisik'   => 100000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pemeriksaan_kas', ['plan_audit_id' => $this->plan->id]);
    }

    public function test_simpan_cek_fisik_ditolak_sebelum_auditor_auditee_diisi(): void
    {
        $response = $this->postJson('/api/audit-detail/cek-fisik', [
            'planAuditId' => $this->plan->id,
            'data'        => [['unit' => 'Contoh']],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('pemeriksaan_cek_fisik', ['plan_audit_id' => $this->plan->id]);
    }

    public function test_simpan_cek_fisik_berhasil_setelah_auditor_auditee_diisi(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'cek-fisik',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ])->assertOk();

        $response = $this->postJson('/api/audit-detail/cek-fisik', [
            'planAuditId' => $this->plan->id,
            'data'        => [['unit' => 'Contoh']],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pemeriksaan_cek_fisik', ['plan_audit_id' => $this->plan->id]);
    }

    public function test_auditor_untuk_tool_lain_tidak_ikut_membolehkan_tool_ini(): void
    {
        // Mengisi Auditor/Auditee untuk tool "bank" tidak boleh ikut membuka
        // simpan untuk tool "kas" pada plan yang sama.
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'bank',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ])->assertOk();

        $this->postJson('/api/audit-detail/kas', [
            'plan_audit_id' => $this->plan->id,
            'nama_pos'      => 'Kas Besar',
            'saldo_fisik'   => 100000,
        ])->assertStatus(422);
    }
}
