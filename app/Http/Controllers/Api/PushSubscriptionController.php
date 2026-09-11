<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPush\Vapid;
use App\Services\WebPush\VapidKeyStore;
use App\Services\WebPush\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        $siapMenyimpan = $this->tabelLanggananAda();

        return response()->json([
            'ok' => true,
            'enabled' => $aktif && $siapMenyimpan,
            'publicKey' => $aktif && $siapMenyimpan ? Vapid::dariKonfigurasi()->kunciPublik() : null,
            'subscribed' => $aktif && $siapMenyimpan && $this->sudahBerlangganan($request),

            // Kalau fiturnya belum hidup, jangan cuma bilang "belum aktif" —
            // sebutkan APA yang kurang, dan (untuk admin) tawarkan tombolnya.
            'canActivate' => ! $aktif && $siapMenyimpan && $request->user()?->isAdmin(),
            'needsMigration' => ! $siapMenyimpan,
        ]);
    }

    /**
     * Nyalakan notifikasi push untuk seluruh aplikasi, sekali saja.
     *
     * Kunci VAPID dibuat di server dan disimpan di database; tidak ada yang
     * perlu menyalin apa pun. Kalau kuncinya sudah ada — entah dari .env atau
     * dari penekanan tombol sebelumnya — yang lama dipakai terus, TIDAK ditimpa:
     * mengganti pasangan kunci membuat semua langganan yang sudah terdaftar
     * ditolak server push.
     */
    public function aktifkan(Request $request): JsonResponse
    {
        if (! $this->tabelLanggananAda()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tabel push_subscriptions belum ada. Jalankan /deploy/migrate lebih dulu.',
            ], 422);
        }

        $sudahAda = WebPushSender::siap();
        VapidKeyStore::pasangSekali($request->user()?->username);

        return response()->json([
            'ok' => true,
            'enabled' => true,
            'publicKey' => Vapid::dariKonfigurasi()->kunciPublik(),
            'message' => $sudahAda
                ? 'Notifikasi push memang sudah aktif sejak sebelumnya.'
                : 'Notifikasi push diaktifkan. Sekarang setiap pengguna bisa menyalakannya di perangkat masing-masing.',
        ]);
    }

    /** Migrasi produksi dijalankan manual, jadi tabelnya bisa saja belum ada. */
    private function tabelLanggananAda(): bool
    {
        try {
            return Schema::hasTable('push_subscriptions');
        } catch (\Throwable) {
            return false;
        }
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
