<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\PlanTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Satu plan = satu pelaksanaan audit, bukan satu pelaksanaan per orang.
 *
 * Plan menugaskan Kepala Tim + anggota Tim Audit, dan tiap orang punya barisnya
 * sendiri di daftar Task. Tapi auditnya dikerjakan bersama SEKALI: begitu salah
 * satu merekam Mulai/Selesai, task anggota lain pada plan yang sama ikut
 * tertutup dengan data yang sama — tidak ada pengisian dobel.
 *
 * Task cabang (assigned_to = nama cabang) bukan pelaksanaan audit, jadi tidak
 * ikut tertutup.
 */
class TaskPelaksanaanSatuPlanTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0461/01/09/2026/SPT-IAT',
            'cabang'      => 'WHS PART AVIAN',
            'jenis_audit' => 'Audit Serah Terima Warehouse',
            'status'      => 'running',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => ['Heri Syahputra', 'Salim'],
        ]);

        app(PlanTaskService::class)->syncPlan($this->plan);
    }

    private function taskMilik(string $nama): AuditTask
    {
        return AuditTask::where('plan_audit_id', $this->plan->id)
            ->where('assigned_to', $nama)
            ->firstOrFail();
    }

    public function test_kepala_tim_merekam_pelaksanaan_menutup_task_seluruh_tim(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $res = $this->postJson('/api/tasks/' . $this->taskMilik('Abdul Aziz')->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-12',
        ]);

        $res->assertOk();

        foreach (['Abdul Aziz', 'Heri Syahputra', 'Salim'] as $nama) {
            $task = $this->taskMilik($nama);
            $this->assertSame('done', $task->status, "Task {$nama} seharusnya ikut selesai");
            $this->assertSame('2026-09-11', $task->started_at->toDateString());
            $this->assertSame('2026-09-12', $task->finished_at->toDateString());
        }
    }

    public function test_anggota_tim_yang_merekam_juga_menutup_task_kepala_tim(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Salim']));

        $this->postJson('/api/tasks/' . $this->taskMilik('Salim')->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-11',
        ])->assertOk();

        $this->assertSame('done', $this->taskMilik('Abdul Aziz')->status);
        $this->assertSame('done', $this->taskMilik('Heri Syahputra')->status);
        $this->assertStringContainsString(
            'Pelaksanaan direkam oleh Salim',
            (string) $this->taskMilik('Abdul Aziz')->catatan
        );
    }

    public function test_task_cabang_tidak_ikut_tertutup(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $this->postJson('/api/tasks/' . $this->taskMilik('Abdul Aziz')->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-12',
        ])->assertOk();

        $this->assertSame('todo', $this->taskMilik('WHS PART AVIAN')->status);
    }

    public function test_plan_lain_tidak_ikut_tertutup(): void
    {
        $planLain = PlanAudit::query()->create([
            'no_spt'      => '0462/01/09/2026/SPT-IAT',
            'cabang'      => 'SO BBT',
            'jenis_audit' => 'Audit Kas',
            'status'      => 'running',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => [],
        ]);
        app(PlanTaskService::class)->syncPlan($planLain);

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $this->postJson('/api/tasks/' . $this->taskMilik('Abdul Aziz')->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-12',
        ])->assertOk();

        $taskPlanLain = AuditTask::where('plan_audit_id', $planLain->id)
            ->where('assigned_to', 'Abdul Aziz')
            ->firstOrFail();

        $this->assertSame('todo', $taskPlanLain->status);
    }

    public function test_perbaikan_pelaksanaan_memperbarui_seluruh_tim_tanpa_menumpuk_catatan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));
        $taskKetua = $this->taskMilik('Abdul Aziz');

        $this->postJson('/api/tasks/' . $taskKetua->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-12',
        ])->assertOk();

        $this->postJson('/api/tasks/' . $taskKetua->id . '/execute', [
            'started_at'  => '2026-09-11',
            'finished_at' => '2026-09-13',
        ])->assertOk();

        $anggota = $this->taskMilik('Heri Syahputra');
        $this->assertSame('2026-09-13', $anggota->finished_at->toDateString());
        $this->assertSame(1, substr_count((string) $anggota->catatan, 'Pelaksanaan direkam oleh'));
    }
}
