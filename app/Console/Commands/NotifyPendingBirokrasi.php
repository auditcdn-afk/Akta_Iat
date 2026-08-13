<?php

namespace App\Console\Commands;

use App\Models\AuditRecommendation;
use App\Models\SuratKeputusan;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class NotifyPendingBirokrasi extends Command
{
    /**
     * Reminder harian: kirim ulang notifikasi ke penerima step birokrasi
     * rekomendasi & tahap approval SK yang masih pending, supaya tidak lupa
     * mengisi. NotificationDispatcher sendiri yang menahan pengiriman ulang
     * kalau belum lewat jeda ~20 jam sejak notifikasi terakhir.
     *
     * Contoh:
     *   php artisan akta:notify-pending-birokrasi
     */
    protected $signature = 'akta:notify-pending-birokrasi';

    protected $description = 'Reminder harian untuk step birokrasi rekomendasi & approval SK yang masih pending.';

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

        $this->info(sprintf(
            'Selesai. %d rekomendasi & %d SK diperiksa.',
            $recommendations->count(),
            $suratKeputusans->count()
        ));

        return self::SUCCESS;
    }
}
