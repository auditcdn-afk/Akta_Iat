<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\BuPerformance;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\PlanTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Plan yang sudah SELESAI atau DIBATALKAN tidak boleh meninggalkan task
 * "Belum Dikerjakan" di daftar auditor.
 *
 * Task hanya tertutup kalau ada yang merekam pelaksanaannya, sementara plan
 * bisa ditutup lewat jalur lain — seluruh tahapnya dilewatkan admin, atau
 * dinyatakan selesai/dibatalkan dari halaman Plan Audit. Dulu jalur itu sama
 * sekali tidak menyentuh task-nya, jadi barisnya menumpuk di daftar auditor
 * selamanya padahal tidak ada lagi yang perlu dikerjakan.
 */
class PlanSelesaiTutupTaskTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $status, string $noSpt = '0073/06/08/2026/SPT-IAT'): PlanAudit
    {
        $plan = PlanAudit::query()->create([
            'no_spt'      => $noSpt,
            'cabang'      => 'SO BBT',
            'jenis_audit' => 'Audit Full SO',
            'status'      => $status,
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => [],
        ]);

        app(PlanTaskService::class)->syncPlan($plan);

        return $plan;
    }

    private function taskTerbuka(PlanAudit $plan): int
    {
        return AuditTask::where('plan_audit_id', $plan->id)->where('status', '!=', 'done')->count();
    }

    public function test_plan_dinyatakan_selesai_menutup_task_auditor(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $plan = $this->plan('cabang_active');
        $this->assertSame(1, $this->taskTerbuka($plan));

        // Syarat menyatakan selesai: BU Performance unit usaha itu sudah ada.
        BuPerformance::query()->create([
            'bulan' => now()->format('Y-m'), 'unit_usaha' => 'SO BBT',
            'auditor' => 'Abdul Aziz', 'penilaian' => [],
        ]);

        // cabang_active -> done
        $this->postJson("/api/plans/{$plan->id}/advance")->assertOk();

        $this->assertSame('done', $plan->fresh()->status);
        $this->assertSame(0, $this->taskTerbuka($plan));
    }

    /** 'cancelled' bukan status yang boleh dipilih admin-reset; dipakai 'done'. */
    public function test_admin_menutup_plan_juga_menutup_task(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $plan = $this->plan('running');
        $this->assertGreaterThan(0, $this->taskTerbuka($plan));

        $this->postJson("/api/plans/{$plan->id}/admin-reset", [
            'status' => 'done',
            'alasan' => 'Sudah diaudit di luar sistem',
        ])->assertOk();

        $this->assertSame(0, $this->taskTerbuka($plan));
    }

    /** Plan lama yang sudah selesai ikut dirapikan saat sinkronisasi berjalan. */
    public function test_plan_lama_yang_sudah_selesai_ikut_dirapikan(): void
    {
        $plan = $this->plan('running');
        $plan->update(['status' => 'done']);          // ditutup tanpa lewat controller
        $this->assertGreaterThan(0, $this->taskTerbuka($plan));

        app(PlanTaskService::class)->syncAll();

        $this->assertSame(0, $this->taskTerbuka($plan));
    }

    /** Plan yang masih berjalan TIDAK boleh ikut tertutup. */
    public function test_plan_yang_masih_berjalan_tasknya_tetap_ada(): void
    {
        $plan = $this->plan('running');

        app(PlanTaskService::class)->syncAll();

        $this->assertGreaterThan(0, $this->taskTerbuka($plan));
    }

    /** Barisnya ditandai selesai, bukan dihapus — riwayatnya tetap utuh. */
    public function test_task_tidak_dihapus_hanya_ditandai_selesai(): void
    {
        $plan = $this->plan('running');
        $jumlah = AuditTask::where('plan_audit_id', $plan->id)->count();

        $plan->update(['status' => 'done']);
        app(PlanTaskService::class)->syncAll();

        $this->assertSame($jumlah, AuditTask::where('plan_audit_id', $plan->id)->count());
        $this->assertSame($jumlah, AuditTask::where('plan_audit_id', $plan->id)->where('status', 'done')->count());
    }
}
