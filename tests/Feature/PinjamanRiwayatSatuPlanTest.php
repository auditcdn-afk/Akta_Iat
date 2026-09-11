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
 * Riwayat pengajuan BPK/BPB pada sebuah plan harus terlihat oleh siapa pun yang
 * membuka task plan itu — kalau tidak, auditor tidak tahu apakah pinjamannya
 * sudah diajukan atau belum, dan berisiko mengajukan dua kali.
 */
class PinjamanRiwayatSatuPlanTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0461/01/09/2026/SPT-IAT',
            'cabang'      => 'WHS PART AVIAN',
            'jenis_audit' => 'Audit Serah Terima Warehouse',
            'status'      => 'running',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => ['Salim'],
        ]);

        app(PlanTaskService::class)->syncPlan($this->plan);
    }

    private function taskMilik(string $nama): AuditTask
    {
        return AuditTask::where('plan_audit_id', $this->plan->id)
            ->where('assigned_to', $nama)
            ->firstOrFail();
    }

    private function ajukanBpk(int $taskId): array
    {
        // Persis seperti yang dikirim form: cabang_realisasi berupa teks JSON.
        return $this->postJson('/api/pinjaman-cabang', [
            'audit_task_id'    => $taskId,
            'jenis'            => 'BPK',
            'cabang_realisasi' => json_encode(['SO ARK']),
            'no_spd'           => '6705/CDN/TR/09/26',
            'nominal'          => 5000000,
            'terbilang'        => 'Lima Juta Rupiah',
            'catatan'          => 'BPK SO ARK',
        ])->assertCreated()->json('data');
    }

    public function test_cabang_realisasi_tersimpan_sebagai_daftar_bukan_teks_json(): void
    {
        $data = $this->ajukanBpk($this->taskMilik('Abdul Aziz')->id);

        $this->assertSame(['SO ARK'], $data['cabangRealisasi']);
    }

    public function test_pengajuan_muncul_saat_task_yang_sama_dibuka_lagi(): void
    {
        $task = $this->taskMilik('Abdul Aziz');
        $this->ajukanBpk($task->id);

        $rows = $this->getJson('/api/pinjaman-cabang?audit_task_id=' . $task->id)
            ->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame(['SO ARK'], $rows[0]['cabangRealisasi']);
        $this->assertSame('6705/CDN/TR/09/26', $rows[0]['noSpd']);
    }

    public function test_pengajuan_terlihat_dari_task_anggota_tim_lain_pada_plan_yang_sama(): void
    {
        $this->ajukanBpk($this->taskMilik('Abdul Aziz')->id);

        $rows = $this->getJson('/api/pinjaman-cabang?audit_task_id=' . $this->taskMilik('Salim')->id)
            ->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('BPK', $rows[0]['jenis']);
    }

    public function test_pengajuan_plan_lain_tidak_ikut_terbawa(): void
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

        $this->ajukanBpk($this->taskMilik('Abdul Aziz')->id);

        $taskPlanLain = AuditTask::where('plan_audit_id', $planLain->id)
            ->where('assigned_to', 'Abdul Aziz')->firstOrFail();

        $rows = $this->getJson('/api/pinjaman-cabang?audit_task_id=' . $taskPlanLain->id)
            ->assertOk()->json('data');

        $this->assertCount(0, $rows);
    }
}
