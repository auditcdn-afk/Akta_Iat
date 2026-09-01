<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PinjamanCabang;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Role 'unit' dan 'bpk' TIDAK PERNAH ditugaskan langsung ke sebuah task
 * (assigned_to task selalu nama cabang/auditor) — tanpa aturan khusus, mereka
 * jatuh ke filter "hanya task milik sendiri" seperti auditor biasa, dan
 * daftar task-nya SELALU kosong walau ada pinjaman cabang yang sesungguhnya
 * menunggu approval mereka (status pending_unit/pending_bpk). Akibatnya
 * mereka tidak pernah bisa membuka task manapun untuk sampai ke bagian
 * approval pinjaman, termasuk tautan cetak Memo Pinjaman di dalamnya.
 */
class PinjamanApprovalTaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private AuditTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = PlanAudit::query()->create([
            'no_spt' => '0001/TEST/SPT-IAT', 'cabang' => 'CSC TEST',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
            'kepala_tim' => 'Heri Syahputra', 'tim' => ['Heri Syahputra'],
        ]);
        $this->task = AuditTask::create([
            'plan_audit_id' => $plan->id, 'judul' => 'Audit Full CSC - CSC TEST',
            'assigned_to' => 'CSC TEST', 'status' => 'todo', 'created_by' => 'admin',
        ]);
    }

    public function test_role_unit_melihat_task_yang_pinjaman_bpk_nya_menunggu_dia(): void
    {
        PinjamanCabang::create([
            'audit_task_id' => $this->task->id, 'jenis' => 'BPK',
            'cabang_realisasi' => ['POS AGL'], 'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'status' => 'pending_unit', 'approvals' => [], 'created_by' => 'heris',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'unit', 'username' => 'unituser']));

        $data = $this->get('/api/tasks')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($this->task->id, $data[0]['id']);
    }

    public function test_role_bpk_melihat_task_yang_pinjaman_bpk_atau_bpb_nya_menunggu_dia(): void
    {
        PinjamanCabang::create([
            'audit_task_id' => $this->task->id, 'jenis' => 'BPB',
            'departemen' => 'Finance', 'nominal' => 1000000, 'terbilang' => 'Satu Juta Rupiah',
            'status' => 'pending_bpk', 'approvals' => [], 'created_by' => 'heris',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'bpk', 'username' => 'bpkuser']));

        $data = $this->get('/api/tasks')->assertOk()->json('data');
        $this->assertCount(1, $data);
    }

    public function test_role_unit_tidak_melihat_task_yang_pinjamannya_belum_sampai_tahap_unit(): void
    {
        PinjamanCabang::create([
            'audit_task_id' => $this->task->id, 'jenis' => 'BPK',
            'cabang_realisasi' => ['POS AGL'], 'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'status' => 'pending_koordinator', 'approvals' => [], 'created_by' => 'heris',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'unit', 'username' => 'unituser']));

        $data = $this->get('/api/tasks')->assertOk()->json('data');
        $this->assertCount(0, $data);
    }

    public function test_role_unit_tidak_lagi_melihat_task_setelah_pinjaman_disetujui_olehnya(): void
    {
        PinjamanCabang::create([
            'audit_task_id' => $this->task->id, 'jenis' => 'BPK',
            'cabang_realisasi' => ['POS AGL'], 'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'status' => 'pending_bpk', // sudah lewat tahap unit
            'approvals' => [], 'created_by' => 'heris',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'unit', 'username' => 'unituser']));

        $data = $this->get('/api/tasks')->assertOk()->json('data');
        $this->assertCount(0, $data);
    }
}
