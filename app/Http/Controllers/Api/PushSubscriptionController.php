<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPush\Vapid;
use App\Services\WebPush\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Kunci publik VAPID + status fitur.
     *
     * Dipanggil sebelum tombol "Aktifkan notifikasi" ditampilkan: kalau server
     * belum disiapkan (kunci belum diisi di .env), tombolnya tidak usah muncul
     * sama sekali daripada muncul lalu gagal saat ditekan.
     */
    public function publicKey(Request $request): JsonResponse
    {
        $aktif = WebPushSender::siap();

        return response()->json([
            'ok' => true,
            'enabled' => $aktif,
            'publicKey' => $aktif ? Vapid::dariKonfigurasi()->kunciPublik() : null,
            'subscribed' => $aktif && $this->sudahBerlangganan($request),
        ]);
    }

    /**
     * Migrasi produksi dijalankan manual (lihat deploy.yml), jadi tabelnya bisa
     * saja belum ada sesaat setelah rilis. Itu bukan alasan untuk membalas 500
     * dan membuat seluruh panel notifikasi tampak rusak.
     */
    private function sudahBerlangganan(Request $request): bool
    {
        try {
            return PushSubscription::query()->where('user_id', $request->user()?->id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Simpan (atau pindahkan kepemilikan) satu langganan perangkat.
     *
     * Browser memanggil ini setiap kali aplikasi dibuka, bukan hanya saat
     * pertama mengizinkan: langganan bisa diperbarui sendiri oleh browser, dan
     * satu perangkat bisa dipakai bergantian oleh dua akun. Karena itu
     * kuncinya endpoint — yang sama akan diperbarui, bukan digandakan.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:128'],
            'keys.auth' => ['required', 'string', 'max:32'],
        ]);

        $langganan = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'perangkat' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_failed_at' => null,
                'last_error' => null,
            ]
        );

        return response()->json(['ok' => true, 'id' => $langganan->id]);
    }

    /** Pengguna mematikan notifikasi di perangkat ini. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Kirim notifikasi percobaan ke perangkat ini, dan laporkan hasil sebenarnya.
     *
     * Sengaja dikirim langsung (tidak lewat antrean) supaya pengguna mendapat
     * jawaban jujur saat itu juga: berhasil, atau pesan kesalahan dari server
     * push. Ini satu-satunya tempat push dikirim di dalam permintaan pengguna,
     * dan itu memang yang diminta tombolnya.
     */
    public function test(Request $request): JsonResponse
    {
        if (! WebPushSender::siap()) {
            return response()->json([
                'ok' => false,
                'message' => 'Kunci VAPID belum dipasang di server.',
            ], 422);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        $langganan = PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $langganan) {
            return response()->json([
                'ok' => false,
                'message' => 'Perangkat ini belum terdaftar. Aktifkan notifikasinya dulu.',
            ], 404);
        }

        $terkirim = (new WebPushSender(Vapid::dariKonfigurasi()))->kirim($langganan, [
            'title' => 'Notifikasi percobaan',
            'body' => 'Berhasil! Notifikasi SIMPAS-IAT akan muncul seperti ini.',
            'url' => '/akta/dashboard',
            'tag' => 'akta-test',
        ]);

        return response()->json([
            'ok' => $terkirim,
            'message' => $terkirim
                ? 'Notifikasi percobaan dikirim.'
                : ($langganan->last_error ?: 'Server push menolak kiriman. Coba aktifkan ulang notifikasinya.'),
        ], $terkirim ? 200 : 422);
    }
}
