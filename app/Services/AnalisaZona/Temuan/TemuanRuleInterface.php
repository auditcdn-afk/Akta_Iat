<?php

namespace App\Services\AnalisaZona\Temuan;

/**
 * Kontrak satu aturan pemeriksaan otomatis. Menambah pemeriksaan baru =
 * buat 1 kelas baru yang implement ini lalu daftarkan di TemuanRuleRegistry
 * — pola yang sama dengan parser file (AnalisaFileParserInterface).
 *
 * Aturan yang TIDAK menemukan apa-apa mengembalikan array kosong, dan itu
 * hasil yang sah (bukan kegagalan): artinya untuk periode itu memang tidak
 * ada yang perlu ditindak.
 */
interface TemuanRuleInterface
{
    /** Kode stabil aturan ini, dipakai di kolom `kode_rule`. */
    public function kode(): string;

    /**
     * @param string $unitUsahaCode Kode unit usaha yang sudah dinormalisasi.
     * @param string $start         Tanggal awal periode (Y-m-d).
     * @param string $end           Tanggal akhir periode (Y-m-d).
     * @return Temuan[]
     */
    public function evaluate(string $unitUsahaCode, string $start, string $end): array;
}
