<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\PushSubscription;
use App\Services\WebPush\Vapid;
use App\Services\WebPush\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mendorong satu notifikasi in-app ke semua perangkat milik penerimanya.
 *
 * Dijalankan di ANTREAN, tidak pernah di dalam permintaan yang sedang dilayani
 * pengguna. Satu approve plan bisa memberi tahu belasan perangkat, dan tiap
 * perangkat berarti satu permintaan HTTPS ke server push (~200-500 ms). Kalau
 * itu dikerjakan inline, tombol Approve akan terasa menggantung beberapa detik —
 * persis keluhan lambat yang sudah susah payah dihilangkan.
 */
class KirimPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Notifikasi ini pelengkap. Kalau server push sedang bermasalah, dua kali
     * percobaan sudah cukup; menumpuk percobaan hanya membebani antrean yang
     * di hosting ini digerakkan cron tiap menit.
     */
    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        if (! WebPushSender::siap()) {
            return;
        }

        $notifikasi = AppNotification::find($this->notificationId);
        if (! $notifikasi) {
            return;
        }

        // Sudah dibaca sebelum antrean sempat jalan (mis. penggunanya memang
        // sedang membuka aplikasi) — tidak perlu lagi membunyikan HP-nya.
        if ($notifikasi->read_at !== null) {
            return;
        }

        $langganan = PushSubscription::query()
            ->where('user_id', $notifikasi->user_id)
            ->get();

        if ($langganan->isEmpty()) {
            return;
        }

        $pengirim = new WebPushSender(Vapid::dariKonfigurasi());

        $isi = [
            'title' => $notifikasi->title,
            'body' => (string) $notifikasi->message,
            'url' => $notifikasi->url ?: '/akta/dashboard',
            // tag: notifikasi susulan untuk hal yang sama menimpa yang lama,
            // bukan menumpuk jadi sepuluh baris tentang plan yang sama.
            'tag' => $notifikasi->notifiable_type
                ? 'akta-'.class_basename($notifikasi->notifiable_type).'-'.$notifikasi->notifiable_id
                : 'akta-notif-'.$notifikasi->id,
            'notificationId' => $notifikasi->id,
        ];

        foreach ($langganan as $satu) {
            $pengirim->kirim($satu, $isi);
        }
    }
}
