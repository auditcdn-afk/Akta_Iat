<?php

namespace App\Services\WebPush;

use RuntimeException;

/**
 * Enkripsi isi Web Push sesuai RFC 8291 ("Message Encryption for Web Push"),
 * skema aes128gcm.
 *
 * Intinya: server tidak boleh mempercayai perantara. Notifikasi dititipkan ke
 * server push milik Google/Mozilla/Apple, jadi isinya dienkripsi dulu dengan
 * kunci yang hanya dimiliki browser penerima — perantara cuma melihat byte acak.
 *
 * Alurnya:
 *   1. buat sepasang kunci P-256 sekali pakai (ephemeral),
 *   2. ECDH dengan kunci publik browser -> rahasia bersama,
 *   3. rahasia itu + "auth secret" browser diregangkan lewat HKDF jadi kunci
 *      AES-128-GCM dan nonce,
 *   4. hasil akhirnya satu rekaman: salt | ukuran rekaman | kunci publik
 *      ephemeral | ciphertext.
 *
 * Semua primitifnya bawaan PHP (openssl + hash_hkdf), tanpa pustaka tambahan.
 */
final class PushEncryptor
{
    /** Info HKDF sesuai RFC 8291 §3.3 — beda satu byte saja, browser gagal membuka. */
    private const INFO_IKM = "WebPush: info\x00";
    private const INFO_CEK = "Content-Encoding: aes128gcm\x00";
    private const INFO_NONCE = "Content-Encoding: nonce\x00";

    private const PANJANG_SALT = 16;
    private const PANJANG_CEK = 16;
    private const PANJANG_NONCE = 12;

    /**
     * Ukuran rekaman yang diumumkan ke penerima. Isi notifikasi kita jauh lebih
     * kecil dari ini, jadi selalu muat dalam satu rekaman — tidak perlu memecah.
     */
    private const UKURAN_REKAMAN = 4096;

    /** Batas aman isi sebelum enkripsi, menyisakan ruang untuk padding & tag GCM. */
    public const MAKS_ISI = self::UKURAN_REKAMAN - 103;

    /**
     * @param  string  $isi           JSON notifikasi (byte mentah).
     * @param  string  $kunciPublikUa Kunci p256dh dari langganan browser, 65 byte mentah.
     * @param  string  $authUa        Auth secret dari langganan browser, 16 byte mentah.
     * @param  string|null $saltPaksa Hanya untuk pengujian; produksi selalu acak.
     * @return string Badan permintaan HTTP yang siap dikirim ke endpoint push.
     */
    public static function enkripsi(string $isi, string $kunciPublikUa, string $authUa, ?string $saltPaksa = null): string
    {
        if (strlen($isi) > self::MAKS_ISI) {
            throw new RuntimeException('Isi notifikasi terlalu panjang untuk satu rekaman Web Push.');
        }
        if (strlen($authUa) !== 16) {
            throw new RuntimeException('Auth secret dari browser harus 16 byte.');
        }

        $ua = P256::kunciPublik($kunciPublikUa);
        $ephemeral = P256::pasanganKunciBaru();
        $kunciEphemeral = P256::kunciPrivat($ephemeral['privat'], $ephemeral['publik']);

        $rahasiaBersama = openssl_pkey_derive($ua, $kunciEphemeral);
        if ($rahasiaBersama === false) {
            throw new RuntimeException('ECDH gagal — kunci publik langganan kemungkinan rusak.');
        }

        // Urutan di INFO_IKM wajib: kunci penerima dulu, baru kunci pengirim.
        $ikm = hash_hkdf(
            'sha256',
            $rahasiaBersama,
            32,
            self::INFO_IKM.$kunciPublikUa.$ephemeral['publik'],
            $authUa
        );

        $salt = $saltPaksa ?? random_bytes(self::PANJANG_SALT);
        $cek = hash_hkdf('sha256', $ikm, self::PANJANG_CEK, self::INFO_CEK, $salt);
        $nonce = hash_hkdf('sha256', $ikm, self::PANJANG_NONCE, self::INFO_NONCE, $salt);

        // 0x02 = penanda "ini rekaman terakhir" (RFC 8188 §2). Karena isinya
        // selalu muat sekali jalan, tidak ada padding lain yang ditambahkan.
        $tag = '';
        $ciphertext = openssl_encrypt(
            $isi."\x02",
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Enkripsi AES-128-GCM gagal.');
        }

        // Header rekaman RFC 8188: salt(16) | rs(4, big-endian) | panjang kunci(1) | kunci(65)
        return $salt
            .pack('N', self::UKURAN_REKAMAN)
            .chr(P256::PANJANG_TITIK)
            .$ephemeral['publik']
            .$ciphertext
            .$tag;
    }
}
