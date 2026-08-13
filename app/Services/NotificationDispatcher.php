<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditRecommendation;
use App\Models\SuratKeputusan;

class NotificationDispatcher
{
    /** Jangan kirim notifikasi baru untuk (user, step) yang sama sebelum jeda ini lewat. */
    private const REMINDER_INTERVAL_HOURS = 20;

    /** Notifikasi penerima step pending pertama (yang belum diisi) pada rekomendasi. */
    public static function notifyRecommendationStep(AuditRecommendation $recommendation): void
    {
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
    }

    /** Tandai notifikasi step tertentu (semua penerima) sudah terbaca, karena step-nya sudah diisi. */
    public static function resolveRecommendationStep(AuditRecommendation $recommendation, int $stepIndex): void
    {
        AppNotification::query()
            ->where('notifiable_type', AuditRecommendation::class)
            ->where('notifiable_id', $recommendation->id)
            ->where('step_key', (string) $stepIndex)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /** Notifikasi penerima tahap SK yang sedang pending (manajer/afd). */
    public static function notifySuratKeputusanStep(SuratKeputusan $suratKeputusan): void
    {
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
    }

    /** Tandai notifikasi tahap SK tertentu sudah terbaca, karena tahap itu sudah di-approve/ditolak. */
    public static function resolveSuratKeputusanStage(SuratKeputusan $suratKeputusan, string $stage): void
    {
        AppNotification::query()
            ->where('notifiable_type', SuratKeputusan::class)
            ->where('notifiable_id', $suratKeputusan->id)
            ->where('step_key', $stage)
            ->unread()
            ->update(['read_at' => now()]);
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
