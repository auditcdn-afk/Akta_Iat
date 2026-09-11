<?php

namespace App\Services\WebPush;

use RuntimeException;

/**
 * VAPID (RFC 8292): cara server memperkenalkan diri ke server push.
 *
 * Tanpa ini siapa pun yang tahu URL endpoint sebuah langganan bisa mengirim
 * notifikasi atas nama kita. Dengan VAPID, tiap permintaan membawa JWT yang
 * ditandatangani kunci privat milik server ini — dan browser sudah menyimpan
 * kunci publiknya sejak pengguna berlangganan, jadi pemalsuan langsung ditolak.
 *
 * Sepasang kunci dibuat sekali lewat `php artisan akta:vapid-keys`, lalu
 * ditaruh di .env server. Kunci privatnya TIDAK boleh berganti setelah ada yang
 * berlangganan: semua langganan lama akan tertolak dan pengguna harus
 * mengaktifkan ulang notifikasinya.
 */
final class Vapid
{
    /** Umur JWT. RFC 8292 membatasi maksimal 24 jam; 12 jam memberi ruang bila jam server agak meleset. */
    private const UMUR_TOKEN_DETIK = 12 * 3600;

    public function __construct(
        private readonly string $kunciPublik,
        private readonly string $kunciPrivat,
        private readonly string $subjek,
    ) {}

    public static function dariKonfigurasi(): self
    {
        $pasangan = VapidKeyStore::pasangan() ?? ['publik' => '', 'privat' => ''];

        return new self(
            $pasangan['publik'],
            $pasangan['privat'],
            (string) config('webpush.subject'),
        );
    }

    /** @return array{privat: string, publik: string} base64url, siap ditempel ke .env */
    public static function pasanganKunciBaru(): array
    {
        $pasangan = P256::pasanganKunciBaru();

        return [
            'privat' => Base64Url::encode($pasangan['privat']),
            'publik' => Base64Url::encode($pasangan['publik']),
        ];
    }

    public function kunciPublik(): string
    {
        return $this->kunciPublik;
    }

    /**
     * Nilai header Authorization untuk satu endpoint push.
     *
     * "aud" wajib berisi origin server push yang dituju (mis.
     * https://fcm.googleapis.com) — bukan URL lengkap endpoint-nya.
     */
    public function headerAuthorization(string $endpoint): string
    {
        $bagian = parse_url($endpoint);
        if (! isset($bagian['scheme'], $bagian['host'])) {
            throw new RuntimeException('Endpoint push tidak berbentuk URL yang sah.');
        }
        $audience = $bagian['scheme'].'://'.$bagian['host'];

        $header = Base64Url::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $klaim = Base64Url::encode(json_encode([
            'aud' => $audience,
            'exp' => time() + self::UMUR_TOKEN_DETIK,
            'sub' => $this->subjek,
        ], JSON_THROW_ON_ERROR));

        $kunci = P256::kunciPrivat(
            Base64Url::decode($this->kunciPrivat),
            Base64Url::decode($this->kunciPublik)
        );

        if (! openssl_sign("{$header}.{$klaim}", $der, $kunci, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani token VAPID.');
        }

        $jwt = $header.'.'.$klaim.'.'.Base64Url::encode(P256::derKeTandaTanganMentah($der));

        return "vapid t={$jwt}, k={$this->kunciPublik}";
    }
}
