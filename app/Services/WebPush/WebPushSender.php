<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengirim satu notifikasi ke satu perangkat.
 *
 * Kelas ini sengaja tidak pernah melempar exception ke pemanggilnya: sebuah
 * langganan yang rusak tidak boleh menghentikan pengiriman ke perangkat lain,
 * apalagi menggagalkan pekerjaan yang memicunya.
 */
final class WebPushSender
{
    public function __construct(private readonly Vapid $vapid) {}

    public static function siap(): bool
    {
        return config('webpush.public_key') !== '' && config('webpush.private_key') !== '';
    }

    /**
     * @param  array<string, mixed>  $isi  Payload yang akan dibaca service worker.
     * @return bool true bila server push menerimanya.
     */
    public function kirim(PushSubscription $langganan, array $isi): bool
    {
        try {
            $badan = PushEncryptor::enkripsi(
                json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                Base64Url::decode($langganan->p256dh),
                Base64Url::decode($langganan->auth)
            );

            $respons = Http::withHeaders([
                    'Authorization' => $this->vapid->headerAuthorization($langganan->endpoint),
                    'Content-Encoding' => 'aes128gcm',
                    'Content-Type' => 'application/octet-stream',
                    'TTL' => (string) config('webpush.ttl'),
                    'Urgency' => 'normal',
                ])
                ->timeout((int) config('webpush.timeout'))
                ->withBody($badan, 'application/octet-stream')
                ->post($langganan->endpoint);
        } catch (Throwable $e) {
            $this->catatGagal($langganan, 'Tidak terkirim: '.$e->getMessage());

            return false;
        }

        if ($respons->successful()) {
            $langganan->forceFill([
                'last_success_at' => now(),
                'last_failed_at' => null,
                'last_error' => null,
            ])->save();

            return true;
        }

        // 404/410 = langganan sudah tidak ada di sisi server push: aplikasi
        // dihapus, data browser dibersihkan, atau perangkat diganti. Menyimpannya
        // hanya membuat tiap notifikasi berikutnya membuang satu permintaan.
        if (in_array($respons->status(), [404, 410], true)) {
            $langganan->delete();

            return false;
        }

        $this->catatGagal($langganan, 'HTTP '.$respons->status().' '.mb_substr($respons->body(), 0, 180));

        return false;
    }

    private function catatGagal(PushSubscription $langganan, string $pesan): void
    {
        try {
            $langganan->forceFill([
                'last_failed_at' => now(),
                'last_error' => mb_substr($pesan, 0, 255),
            ])->save();
        } catch (Throwable) {
            // Mencatat kegagalan tidak boleh jadi sumber kegagalan baru.
        }
    }
}
