<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PlanAudit;
use App\Services\PlanTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu plan menghasilkan satu task per ORANG yang ditugaskan — bukan satu task
 * per plan. Yang tidak boleh terjadi: orang yang sama dapat lebih dari satu
 * task pada plan yang sama (di daftar Task itu terlihat seperti plan dobel),
 * mis. karena namanya ditulis dengan spasi berlebih atau beda huruf besar/kecil
 * di Kepala Tim dan Tim Audit, atau karena sinkronisasi dijalankan dua kali.
 */
class PlanTaskServiceDuplikatTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attrs = []): PlanAudit
    {
        return PlanAudit::query()->create(array_merge([
            'no_spt'      => '0480/01/09/2026/SPT-IAT',
            'cabang'      => 'SO DMI',
            'jenis_audit' => 'Audit Kas + Unit SMH',
            'status'      => 'approved',
            'kepala_tim'  => 'Budi Santoso',
            'tim'         => [],
        ], $attrs));
    }

    public function test_satu_task_per_orang_yang_ditugaskan(): void
    {
        $plan = $this->plan(['tim' => ['Andi Wijaya']]);

        app(PlanTaskService::class)->syncPlan($plan);

        $this->assertSame(2, AuditTask::where('plan_audit_id', $plan->id)->count());
        $this->assertEqualsCanonicalizing(
            ['Budi Santoso', 'Andi Wijaya'],
            AuditTask::where('plan_audit_id', $plan->id)->pluck('assigned_to')->all()
        );
    }

    public function test_nama_sama_dengan_spasi_atau_huruf_beda_tidak_bikin_task_ganda(): void
    {
        $plan = $this->plan(['tim' => ['  budi   santoso ', 'BUDI SANTOSO', 'Andi Wijaya']]);

        app(PlanTaskService::class)->syncPlan($plan);

        $this->assertSame(2, AuditTask::where('plan_audit_id', $plan->id)->count());
    }

    public function test_sync_diulang_tidak_menambah_task(): void
    {
        $plan = $this->plan(['tim' => ['Andi Wijaya']]);
        $svc  = app(PlanTaskService::class);

        $svc->syncPlan($plan);
        $svc->syncPlan($plan);
        $svc->syncAll();
        $svc->syncAll();

        $this->assertSame(2, AuditTask::where('plan_audit_id', $plan->id)->count());
    }

    public function test_plan_berjalan_menambah_satu_task_cabang(): void
    {
        $plan = $this->plan(['status' => 'running']);

        app(PlanTaskService::class)->syncPlan($plan);

        $this->assertSame(2, AuditTask::where('plan_audit_id', $plan->id)->count());
        $this->assertTrue(
            AuditTask::where('plan_audit_id', $plan->id)->where('assigned_to', 'SO DMI')->exists()
        );
    }
}
