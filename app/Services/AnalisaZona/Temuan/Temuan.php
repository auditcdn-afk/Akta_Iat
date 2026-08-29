<?php

namespace App\Services\AnalisaZona\Temuan;

use App\Models\AnalisaTemuan;

/** Satu temuan hasil pemeriksaan otomatis — siap disimpan ke `analisa_temuan`. */
class Temuan
{
    /**
     * @param string      $judul       Ringkasan apa yang ditemukan, sudah berisi angkanya.
     * @param string      $rekomendasi Tindakan konkret yang disarankan — inti dari
     *                                 temuan ini, karena tujuan modul ini memang
     *                                 menyiapkan agenda pemeriksaan, bukan sekadar
     *                                 melaporkan angka.
     * @param string|null $tanggal     Tanggal kejadian (Y-m-d); null kalau temuannya
     *                                 memang tingkat periode, bukan tingkat hari.
     * @param array       $detail      Data pendukung untuk ditampilkan saat didrill —
     *                                 nomor bukti, kode konsumen, rincian selisih, dsb.
     */
    public function __construct(
        public readonly string $judul,
        public readonly string $severity,
        public readonly string $rekomendasi,
        public readonly ?float $nominal = null,
        public readonly ?string $tanggal = null,
        public readonly array $detail = [],
    ) {
    }

    public static function tinggi(string $judul, string $rekomendasi, ?float $nominal = null, ?string $tanggal = null, array $detail = []): self
    {
        return new self($judul, AnalisaTemuan::SEVERITY_TINGGI, $rekomendasi, $nominal, $tanggal, $detail);
    }

    public static function sedang(string $judul, string $rekomendasi, ?float $nominal = null, ?string $tanggal = null, array $detail = []): self
    {
        return new self($judul, AnalisaTemuan::SEVERITY_SEDANG, $rekomendasi, $nominal, $tanggal, $detail);
    }

    public static function rendah(string $judul, string $rekomendasi, ?float $nominal = null, ?string $tanggal = null, array $detail = []): self
    {
        return new self($judul, AnalisaTemuan::SEVERITY_RENDAH, $rekomendasi, $nominal, $tanggal, $detail);
    }
}
