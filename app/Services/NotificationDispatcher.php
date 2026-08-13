<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\SuratKeputusan;
use Throwable;

class NotificationDispatcher
{
    /** Jangan kirim notifikasi baru untuk (user, step) yang sama sebelum jeda ini lewat. */
    private const REMINDER_INTERVAL_HOURS = 20;

    /**
     * Role yang harus memproses Plan Audit di tiap status, sama dengan
     * PlanAuditController::TRANSITIONS[status]['roles'] (minus 'admin',
     * yang di sana cuma override, bukan pemilik gerbang). Status tanpa
     * entry di sini (mis. "done") berarti tidak ada yang perlu diberi tahu.
     */
    private const PLAN_STATUS_ROLES = [
        'draft'               => ['auditor'],
        'pending_koordinator' => ['koordinator'],
        'pending_manajer'     => ['manajer'],
        'pending_coo'         => ['coo'],
        'scheduled'           => ['auditor'],
        'running'             => ['__branch__'],
        'cabang_active'       => ['auditor', 'manajer', '__branch__'],
        'revisi'              => ['auditor'],
    ];

    private const PLAN_STATUS_LABELS = [
        'draft'               => 'Draft',
        'pending_koordinator' => 'Menunggu Koordinator',
        'pending_manajer'     => 'Menunggu Manajer',
        'pending_coo'         => 'Menunggu COO',
        'scheduled'           => 'Disetujui',
        'running'             => 'Audit Berjalan',
        'cabang_active'       => 'Cabang Aktif',
        'revisi'              => 'Perlu Perbaikan',
    ];

    /** Notifikasi penerima step pending pertama (yang belum diisi) pada rekomendasi. */
    public static function notifyRecommendationStep(AuditRecommendation $recommendation): void
    {
        // Migrasi database SENGAJA tidak dijalankan otomatis saat deploy (lihat
        // deploy.yml) -- tabel app_notifications bisa saja belum ada di server
        // untuk sementara. Notifikasi cuma pelengkap; gagal di sini TIDAK boleh
        // menggagalkan pembuatan/approval rekomendasi itu sendiri.
        try {
            $steps = $recommendation->steps ?: [];
            $pendingIndex = null;
            foreach ($steps as $i => $step) {
                if (($step['status'] ?? null) === 'pending') {
                    $pendingIndex = $i;
                    break;
                }
            }
            if ($pendingIndex === null) {
                return;
            }

            $stepRole = $steps[$pendingIndex]['role'] ?? $steps[$pendingIndex]['step'] ?? '';
            if (!$stepRole) {
                return;
            }

            $cabang = $recommendation->planAudit?->cabang ?? '';
            $recipients = BirokrasiResolver::recipientsForStep($stepRole, $cabang);

            $title = 'Giliran mengisi rekomendasi';
            $message = sprintf(
                'Rekomendasi "%s" (%s) menunggu diisi oleh %s.',
                $recommendation->judul,
                $cabang ?: '-',
                $stepRole
            );
            $url = '/akta/rekomendasi?id=' . $recommendation->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'birokrasi_step',
                    AuditRecommendation::class,
                    $recommendation->id,
                    (string) $pendingIndex,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi step tertentu (semua penerima) sudah terbaca, karena step-nya sudah diisi. */
    public static function resolveRecommendationStep(AuditRecommendation $recommendation, int $stepIndex): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', AuditRecommendation::class)
                ->where('notifiable_id', $recommendation->id)
                ->where('step_key', (string) $stepIndex)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Notifikasi penerima tahap SK yang sedang pending (manajer/afd). */
    public static function notifySuratKeputusanStep(SuratKeputusan $suratKeputusan): void
    {
        try {
            $stage = match ($suratKeputusan->status) {
                'pending_manajer' => 'manajer',
                'pending_afd' => 'afd',
                default => null,
            };
            if ($stage === null) {
                return;
            }

            $recipients = BirokrasiResolver::recipientsForRoles([$stage]);

            $title = 'Giliran approve SK';
            $message = sprintf(
                'SK "%s" (%s) menunggu approval %s.',
                $suratKeputusan->no_sk ?: '-',
                $suratKeputusan->unit_usaha ?: '-',
                strtoupper($stage)
            );
            $url = '/akta/sk?id=' . $suratKeputusan->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'sk_step',
                    SuratKeputusan::class,
                    $suratKeputusan->id,
                    $stage,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi tahap SK tertentu sudah terbaca, karena tahap itu sudah di-approve/ditolak. */
    public static function resolveSuratKeputusanStage(SuratKeputusan $suratKeputusan, string $stage): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', SuratKeputusan::class)
                ->where('notifiable_id', $suratKeputusan->id)
                ->where('step_key', $stage)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Notifikasi penerima yang berwenang memproses Plan Audit di status sekarang. */
    public static function notifyPlanAuditStep(PlanAudit $plan): void
    {
        try {
            $roles = self::PLAN_STATUS_ROLES[$plan->status] ?? null;
            if ($roles === null) {
                return;
            }

            $recipients = BirokrasiResolver::recipientsForPlanStatus($roles, $plan->cabang);

            $title = 'Giliran memproses plan audit';
            $message = sprintf(
                'Plan audit "%s" (%s) berstatus "%s".',
                $plan->no_spt,
                $plan->cabang ?: '-',
                self::PLAN_STATUS_LABELS[$plan->status] ?? $plan->status
            );
            $url = '/akta/plan-audit?id=' . $plan->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'plan_audit_step',
                    PlanAudit::class,
                    $plan->id,
                    $plan->status,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi status Plan Audit tertentu sudah terbaca, karena statusnya sudah berubah. */
    public static function resolvePlanAuditStatus(PlanAudit $plan, string $status): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', PlanAudit::class)
                ->where('notifiable_id', $plan->id)
                ->where('step_key', $status)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    private static function notifyIfDue(
        int $userId,
        string $type,
        string $notifiableType,
        int $notifiableId,
        string $stepKey,
        string $title,
        string $message,
        ?string $url
    ): void {
        $last = AppNotification::query()
            ->where('user_id', $userId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('step_key', $stepKey)
            ->latest('id')
            ->first();

        if ($last && $last->created_at && $last->created_at->gt(now()->subHours(self::REMINDER_INTERVAL_HOURS))) {
            return;
        }

        AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'step_key' => $stepKey,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }
}
