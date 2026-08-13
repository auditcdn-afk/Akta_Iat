<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\BirokrasiResolver;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Notifikasi "giliran mengisi" untuk step birokrasi rekomendasi: penerima
 * dicari lewat role/unit_usaha yang cocok persis, self-fill unit sendiri
 * untuk step generik (SO/CSC/WHS), fallback role "manajer" khusus step
 * "Manajer Audit" (slug role aslinya beda dari label step), dan fallback
 * admin kalau tidak ada satu pun yang cocok -- supaya notifikasi tidak
 * hilang begitu saja.
 */
class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipients_for_step_matches_role_atau_unit_usaha(): void
    {
        $byRole = User::factory()->create(['role' => 'afd', 'unit_usaha' => null]);
        $byUnit = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'Retail Aceh']);

        $this->assertTrue(
            BirokrasiResolver::recipientsForStep('AFD', 'SO ALB')->pluck('id')->contains($byRole->id)
        );
        $this->assertTrue(
            BirokrasiResolver::recipientsForStep('Retail Aceh', 'SO TPP')->pluck('id')->contains($byUnit->id)
        );
    }

    public function test_recipients_for_step_self_fill_unit_sendiri(): void
    {
        $branchUser = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);

        $recipients = BirokrasiResolver::recipientsForStep('SO', 'SO ALB');

        $this->assertTrue($recipients->pluck('id')->contains($branchUser->id));
    }

    public function test_recipients_for_step_manajer_audit_jatuh_ke_role_manajer(): void
    {
        $manajer = User::factory()->create(['role' => 'manajer', 'unit_usaha' => null]);

        $recipients = BirokrasiResolver::recipientsForStep('Manajer Audit', 'SO ALB');

        $this->assertTrue($recipients->pluck('id')->contains($manajer->id));
    }

    public function test_recipients_for_step_jatuh_ke_admin_kalau_tidak_ada_yang_cocok(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'unit_usaha' => null]);
        User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'SO BDS']);

        $recipients = BirokrasiResolver::recipientsForStep('AFD', 'SO ALB');

        $this->assertCount(1, $recipients);
        $this->assertSame($admin->id, $recipients->first()->id);
    }

    public function test_notify_recommendation_step_membuat_notifikasi_untuk_step_pending_pertama(): void
    {
        $branchUser = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'running',
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'plan_audit_id' => $plan->id,
            'judul' => 'Rekomendasi uji',
            'prioritas' => 'sedang',
            'status' => 'open',
            'steps' => [
                ['step' => 'created', 'role' => null, 'status' => 'done', 'user' => 'auditor1', 'time' => now(), 'note' => null],
                ['step' => 'SO', 'role' => 'SO', 'status' => 'pending', 'user' => null, 'time' => null, 'note' => null],
                ['step' => 'AFD', 'role' => 'AFD', 'status' => 'pending', 'user' => null, 'time' => null, 'note' => null],
            ],
        ]);

        NotificationDispatcher::notifyRecommendationStep($recommendation);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $branchUser->id,
            'notifiable_type' => AuditRecommendation::class,
            'notifiable_id' => $recommendation->id,
            'step_key' => '1',
        ]);
        $this->assertSame(0, AppNotification::query()->where('step_key', '2')->count());
    }

    public function test_notify_if_due_tidak_dobel_dalam_jeda_reminder(): void
    {
        $branchUser = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'running',
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'plan_audit_id' => $plan->id,
            'judul' => 'Rekomendasi uji dobel',
            'prioritas' => 'sedang',
            'status' => 'open',
            'steps' => [
                ['step' => 'created', 'role' => null, 'status' => 'done', 'user' => 'auditor1', 'time' => now(), 'note' => null],
                ['step' => 'SO', 'role' => 'SO', 'status' => 'pending', 'user' => null, 'time' => null, 'note' => null],
            ],
        ]);

        NotificationDispatcher::notifyRecommendationStep($recommendation);
        NotificationDispatcher::notifyRecommendationStep($recommendation);

        $this->assertSame(
            1,
            AppNotification::query()->where('user_id', $branchUser->id)->count()
        );
    }

    public function test_resolve_recommendation_step_menandai_terbaca(): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0003/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'running',
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'plan_audit_id' => $plan->id,
            'judul' => 'Rekomendasi uji resolve',
            'prioritas' => 'sedang',
            'status' => 'open',
        ]);
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'birokrasi_step',
            'notifiable_type' => AuditRecommendation::class,
            'notifiable_id' => $recommendation->id,
            'step_key' => '1',
            'title' => 'Giliran mengisi rekomendasi',
        ]);

        NotificationDispatcher::resolveRecommendationStep($recommendation, 1);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * Migrasi database SENGAJA tidak dijalankan otomatis saat deploy (lihat
     * deploy.yml) -- tabel app_notifications bisa saja belum ada di server
     * untuk sementara setelah kode baru terkirim. Notifikasi tidak boleh
     * menggagalkan pembuatan/approval rekomendasi & SK itu sendiri kalau ini
     * terjadi.
     */
    public function test_dispatcher_tidak_melempar_error_kalau_tabel_belum_ada(): void
    {
        User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0004/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'running',
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'plan_audit_id' => $plan->id,
            'judul' => 'Rekomendasi uji tanpa tabel notifikasi',
            'prioritas' => 'sedang',
            'status' => 'open',
            'steps' => [
                ['step' => 'created', 'role' => null, 'status' => 'done', 'user' => 'auditor1', 'time' => now(), 'note' => null],
                ['step' => 'SO', 'role' => 'SO', 'status' => 'pending', 'user' => null, 'time' => null, 'note' => null],
            ],
        ]);

        Schema::dropIfExists('app_notifications');

        NotificationDispatcher::notifyRecommendationStep($recommendation);
        NotificationDispatcher::resolveRecommendationStep($recommendation, 0);

        $this->assertTrue(true, 'Tidak ada exception yang dilempar meski tabel app_notifications tidak ada.');
    }

    public function test_recipients_for_plan_status_role_dan_branch(): void
    {
        $koordinator = User::factory()->create(['role' => 'koordinator', 'unit_usaha' => null]);
        $branchUser = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);

        $this->assertTrue(
            BirokrasiResolver::recipientsForPlanStatus(['koordinator'], 'SO ALB')->pluck('id')->contains($koordinator->id)
        );
        $this->assertTrue(
            BirokrasiResolver::recipientsForPlanStatus(['__branch__'], 'SO ALB')->pluck('id')->contains($branchUser->id)
        );
    }

    public function test_recipients_for_plan_status_jatuh_ke_admin_kalau_kosong(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'unit_usaha' => null]);

        $recipients = BirokrasiResolver::recipientsForPlanStatus(['coo'], 'SO ALB');

        $this->assertCount(1, $recipients);
        $this->assertSame($admin->id, $recipients->first()->id);
    }

    public function test_notify_plan_audit_step_membuat_notifikasi_untuk_status_sekarang(): void
    {
        $koordinator = User::factory()->create(['role' => 'koordinator', 'unit_usaha' => null]);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0005/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'pending_koordinator',
        ]);

        NotificationDispatcher::notifyPlanAuditStep($plan);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $koordinator->id,
            'notifiable_type' => PlanAudit::class,
            'notifiable_id' => $plan->id,
            'step_key' => 'pending_koordinator',
        ]);
    }

    public function test_resolve_plan_audit_status_menandai_terbaca(): void
    {
        $user = User::factory()->create(['role' => 'koordinator', 'unit_usaha' => null]);
        $plan = PlanAudit::query()->create([
            'no_spt' => '0006/01/01/2026/SPT-IAT',
            'cabang' => 'SO ALB',
            'jenis_audit' => 'Audit',
            'status' => 'pending_manajer',
        ]);
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'notifiable_type' => PlanAudit::class,
            'notifiable_id' => $plan->id,
            'step_key' => 'pending_koordinator',
            'title' => 'Giliran memproses plan audit',
        ]);

        NotificationDispatcher::resolvePlanAuditStatus($plan, 'pending_koordinator');

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
