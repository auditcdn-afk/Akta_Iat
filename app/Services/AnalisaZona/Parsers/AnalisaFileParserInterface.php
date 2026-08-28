<?php

namespace App\Services\AnalisaZona\Parsers;

use App\Services\AnalisaZona\ParsedFile;

/**
 * Kontrak parser per format file Analisa Zona. Tambah format baru = buat
 * 1 kelas baru yang implement ini, lalu daftarkan di ParserRegistry — tidak
 * perlu ubah kode import/upload yang sudah ada.
 */
interface AnalisaFileParserInterface
{
    /** Nama jenis file ini dipakai di kolom `analisa_uploads.jenis`. */
    public function jenis(): string;

    /** Apakah parser ini yang cocok untuk nama file tersebut? */
    public function supports(string $filename): bool;

    public function parse(string $filename, string $content): ParsedFile;
}
