<?php

namespace App\Services\WebPush;

use App\Models\AppData;
use Throwable;

/**
 * Dari mana kunci VAPID diambil.
 *
 * Urutannya: .env dulu, baru database.
 *
 * .env adalah tempat yang paling tepat secara prinsip — di luar folder web,
 * tidak ikut ter-deploy, tidak tersentuh siapa pun lewat aplikasi. Tapi hosting
 * SIMPAS-IAT hanya menyediakan FTP: menyunting .env berarti mengunduh berkas
 * tersembunyi, mengeditnya, mengunggahnya kembali dengan nama yang tepat, lalu
 * membangun ulang cache konfigurasi. Satu langkah meleset dan fiturnya diam
 * tanpa penjelasan.
 *
 * Karena itu ada jalan kedua: admin menekan satu tombol di aplikasi, kuncinya
 * dibuat di server dan disimpan di tabel app_data. Tidak ada manusia yang
 * perlu melihat, menyalin, atau menempelkan kunci privatnya sama sekali —
 * justru lebih kecil kemungkinan bocornya daripada disalin bolak-balik lewat
 * FTP dan papan klip.
 *
 * CATATAN KEAMANAN: kunci penyimpanannya sengaja TIDAK didaftarkan di
 * App\Support\DataKeys — itu daftar putih untuk endpoint umum /api/data/{key},
 * dan mendaftarkannya di sana akan membuat kunci privat bisa dibaca siapa pun
 * yang sudah login.
 */
final class VapidKeyStore
{
    public const KUNCI = 'webpush_vapid';

    /** @var array{publik: string, privat: string}|false|null null = belum dicari, false = tidak ada */
    private static array|false|null $ingatan = null;

    /** @return array{publik: string, privat: string}|null */
    public static function pasangan(): ?array
    {
        $publikEnv = (string) config('webpush.public_key');
        $privatEnv = (string) config('webpush.private_key');

        if ($publikEnv !== '' && $privatEnv !== '') {
            return ['publik' => $publikEnv, 'privat' => $privatEnv];
        }

        if (self::$ingatan !== null) {
            return self::$ingatan ?: null;
        }

        self::$ingatan = self::bacaDariDatabase() ?? false;

        return self::$ingatan ?: null;
    }

    public static function ada(): bool
    {
        return self::pasangan() !== null;
    }

    /**
     * Pasang kunci sekali seumur aplikasi.
     *
     * Kalau sudah ada, yang lama DIKEMBALIKAN apa adanya — tidak pernah ditimpa.
     * Mengganti pasangan kunci akan membuat seluruh langganan yang sudah
     * terdaftar ditolak server push, dan setiap pengguna harus mengaktifkan
     * notifikasinya ulang tanpa tahu sebabnya.
     *
     * @return array{publik: string, privat: string}
     */
    public static function pasangSekali(?string $aktor = null): array
    {
        $adaSekarang = self::pasangan();
        if ($adaSekarang !== null) {
            return $adaSekarang;
        }

        $baru = Vapid::pasanganKunciBaru();

        // data_key unik di database, jadi kalau dua admin menekan tombolnya
        // bersamaan hanya satu yang menang — yang kalah membaca ulang hasil
        // pemenangnya, bukan menimpanya dengan pasangan kunci lain.
        AppData::query()->firstOrCreate(
            ['data_key' => self::KUNCI],
            [
                'data_value' => ['publik' => $baru['publik'], 'privat' => $baru['privat']],
                'updated_by' => $aktor,
                'updated_at' => now(),
            ]
        );

        self::$ingatan = null;

        return self::pasangan() ?? $baru;
    }

    /** Dipakai setelah penulisan dan di dalam pengujian. */
    public static function lupakanIngatan(): void
    {
        self::$ingatan = null;
    }

    /** @return array{publik: string, privat: string}|null */
    private static function bacaDariDatabase(): ?array
    {
        try {
            $baris = AppData::query()->where('data_key', self::KUNCI)->first();
        } catch (Throwable) {
            // Tabelnya belum ada (migrasi produksi dijalankan manual). Bukan
            // alasan untuk menggagalkan permintaan — fiturnya cukup dianggap mati.
            return null;
        }

        $nilai = $baris?->data_value;

        if (! is_array($nilai) || ($nilai['publik'] ?? '') === '' || ($nilai['privat'] ?? '') === '') {
            return null;
        }

        return ['publik' => (string) $nilai['publik'], 'privat' => (string) $nilai['privat']];
    }
}
