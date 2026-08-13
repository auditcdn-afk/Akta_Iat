<?php

namespace App\Console\Commands;

use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\SuratKeputusan;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class NotifyPendingBirokrasi extends Command
{
    /**
     * Reminder harian: kirim ulang notifikasi ke penerima step birokrasi
     * rekomendasi, tahap approval SK, & status Plan Audit yang masih
     * pending, supaya tidak lupa memproses. NotificationDispatcher sendiri
     * yang menahan pengiriman ulang kalau belum lewat jeda ~20 jam sejak
     * notifikasi terakhir.
     *
     * Contoh:
     *   php artisan akta:notify-pending-birokrasi
     */
    protected $signature = 'akta:notify-pending-birokrasi';

    protected $description = 'Reminder harian untuk step birokrasi rekomendasi, approval SK, & status Plan Audit yang masih pending.';

    public function handle(): int
    {
        $recommendations = AuditRecommendation::query()
            ->whereNotIn('status', ['approved', 'done', 'cancelled'])
            ->with('planAudit')
            ->get();

        foreach ($recommendations as $recommendation) {
            NotificationDispatcher::notifyRecommendationStep($recommendation);
        }

        $suratKeputusans = SuratKeputusan::query()
            ->whereIn('status', ['pending_manajer', 'pending_afd'])
            ->get();

        foreach ($suratKeputusans as $suratKeputusan) {
            NotificationDispatcher::notifySuratKeputusanStep($suratKeputusan);
        }

        $planAudits = PlanAudit::query()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get();

        foreach ($planAudits as $planAudit) {
            NotificationDispatcher::notifyPlanAuditStep($planAudit);
        }

        $this->info(sprintf(
            'Selesai. %d rekomendasi, %d SK, & %d plan audit diperiksa.',
            $recommendations->count(),
            $suratKeputusans->count(),
            $planAudits->count()
        ));

        return self::SUCCESS;
    }
}
