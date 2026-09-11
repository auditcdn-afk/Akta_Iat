<?php

namespace App\Services\WebPush;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Jembatan antara "kunci mentah" yang dipakai Web Push dan OpenSSL.
 *
 * Web Push (RFC 8291/8292) memakai kurva P-256 dalam bentuk paling telanjang:
 * kunci publik = 65 byte titik kurva tak-terkompresi (0x04 || X || Y), kunci
 * privat = 32 byte skalar. OpenSSL tidak bisa menerima bentuk itu langsung — ia
 * menuntut DER/PEM. Kelas ini membungkus byte mentah tadi ke dalam struktur DER
 * yang benar, sehingga seluruh operasi kripto tetap dikerjakan OpenSSL bawaan
 * PHP dan aplikasi tidak perlu pustaka pihak ketiga (folder vendor/ memang tidak
 * ikut ter-deploy ke hosting, lihat .github/workflows/deploy.yml).
 */
final class P256
{
    public const PANJANG_TITIK = 65;
    public const PANJANG_SKALAR = 32;

    /** OID prime256v1 (1.2.840.10045.3.1.7) dan id-ecPublicKey, dipakai di DER di bawah. */
    private const DER_SPKI_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
        ."\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    /** @return array{privat: string, publik: string} keduanya byte mentah */
    public static function pasanganKunciBaru(): array
    {
        $kunci = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($kunci === false) {
            throw new RuntimeException('Gagal membuat kunci P-256 — pastikan ekstensi openssl aktif.');
        }

        $detail = openssl_pkey_get_details($kunci);
        if ($detail === false || ! isset($detail['ec']['d'], $detail['ec']['x'], $detail['ec']['y'])) {
            throw new RuntimeException('Kunci P-256 terbentuk tapi detailnya tidak terbaca.');
        }

        return [
            'privat' => self::padKiri($detail['ec']['d'], self::PANJANG_SKALAR),
            'publik' => "\x04".self::padKiri($detail['ec']['x'], self::PANJANG_SKALAR)
                .self::padKiri($detail['ec']['y'], self::PANJANG_SKALAR),
        ];
    }

    /** Kunci publik 65 byte -> objek OpenSSL, lewat SubjectPublicKeyInfo DER. */
    public static function kunciPublik(string $titik): OpenSSLAsymmetricKey
    {
        if (strlen($titik) !== self::PANJANG_TITIK || $titik[0] !== "\x04") {
            throw new RuntimeException('Kunci publik P-256 harus 65 byte tak-terkompresi (diawali 0x04).');
        }

        $kunci = openssl_pkey_get_public(self::pem('PUBLIC KEY', self::DER_SPKI_PREFIX.$titik));

        if ($kunci === false) {
            throw new RuntimeException('Kunci publik P-256 ditolak OpenSSL.');
        }

        return $kunci;
    }

    /**
     * Skalar privat 32 byte -> objek OpenSSL, lewat ECPrivateKey DER (RFC 5915).
     * Kunci publiknya ikut ditanam karena OpenSSL memerlukannya untuk menandatangani.
     */
    public static function kunciPrivat(string $skalar, string $titikPublik): OpenSSLAsymmetricKey
    {
        if (strlen($skalar) !== self::PANJANG_SKALAR) {
            throw new RuntimeException('Kunci privat P-256 harus tepat 32 byte.');
        }
        if (strlen($titikPublik) !== self::PANJANG_TITIK) {
            throw new RuntimeException('Kunci publik pasangannya harus 65 byte.');
        }

        // SEQUENCE { INTEGER 1, OCTET STRING skalar, [0] OID prime256v1, [1] BIT STRING titik }
        $isi = "\x02\x01\x01"
            ."\x04\x20".$skalar
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".$titikPublik;

        $kunci = openssl_pkey_get_private(self::pem('EC PRIVATE KEY', "\x30".chr(strlen($isi)).$isi));

        if ($kunci === false) {
            throw new RuntimeException('Kunci privat P-256 ditolak OpenSSL.');
        }

        return $kunci;
    }

    /** Titik publik 65 byte milik sebuah kunci privat. */
    public static function titikPublikDari(OpenSSLAsymmetricKey $kunci): string
    {
        $detail = openssl_pkey_get_details($kunci);

        if ($detail === false || ! isset($detail['ec']['x'], $detail['ec']['y'])) {
            throw new RuntimeException('Titik publik tidak terbaca dari kunci.');
        }

        return "\x04".self::padKiri($detail['ec']['x'], self::PANJANG_SKALAR)
            .self::padKiri($detail['ec']['y'], self::PANJANG_SKALAR);
    }

    /**
     * Tanda tangan ECDSA dari OpenSSL berbentuk DER (panjangnya berubah-ubah),
     * sementara JWS ES256 menuntut 64 byte kaku: R dan S masing-masing 32 byte.
     */
    public static function derKeTandaTanganMentah(string $der): string
    {
        $posisi = 0;
        $baca = function (string $data, int &$posisi): string {
            if (($data[$posisi] ?? '') !== "\x02") {
                throw new RuntimeException('Tanda tangan DER rusak: INTEGER tidak ditemukan.');
            }
            $panjang = ord($data[$posisi + 1]);
            $nilai = substr($data, $posisi + 2, $panjang);
            $posisi += 2 + $panjang;

            // DER menambahkan 0x00 di depan bila bit tertinggi menyala, supaya
            // angkanya tidak terbaca negatif. Byte itu bukan bagian dari R/S.
            return str_pad(ltrim($nilai, "\x00"), self::PANJANG_SKALAR, "\x00", STR_PAD_LEFT);
        };

        if (($der[0] ?? '') !== "\x30") {
            throw new RuntimeException('Tanda tangan DER rusak: bukan SEQUENCE.');
        }
        $posisi = 2;

        return $baca($der, $posisi).$baca($der, $posisi);
    }

    private static function padKiri(string $raw, int $panjang): string
    {
        return str_pad(substr($raw, -$panjang), $panjang, "\x00", STR_PAD_LEFT);
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END {$label}-----\n";
    }
}
