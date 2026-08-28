<?php

namespace App\Services\AnalisaZona\Parsers;

use App\Services\AnalisaZona\ParsedFile;

/**
 * Format .RKK (Rekap Kas Kecil per unit usaha per hari):
 *   baris 1: hash sumber
 *   baris 2: kode unit usaha (mis. "SOSGL")
 *   baris berikutnya: baris tipe "1" = header transaksi (no voucher, tanggal,
 *   supplier, keterangan, total), diikuti 1+ baris tipe "2" = detail jurnal
 *   (kode akun, nominal per akun) untuk voucher yang sama.
 */
class RkkParser implements AnalisaFileParserInterface
{
    public function jenis(): string
    {
        return 'rkk';
    }

    public function supports(string $filename): bool
    {
        return str_ends_with(strtolower($filename), '.rkk');
    }

    public function parse(string $filename, string $content): ParsedFile
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        $sourceHash    = trim($lines[0] ?? '');
        $unitUsahaCode = trim($lines[1] ?? '');

        $rows        = [];
        $header      = null; // konteks voucher tipe "1" terakhir
        $fileTanggal = null;

        for ($i = 2; $i < count($lines); $i++) {
            $parts = explode(';', $lines[$i]);
            $tipe  = trim($parts[0] ?? '');

            if ($tipe === '1') {
                $header = [
                    'no_voucher'    => trim($parts[2] ?? ''),
                    'tanggal'       => trim($parts[4] ?? ''),
                    'nama_supplier' => trim($parts[6] ?? ''),
                    'keterangan'    => trim($parts[9] ?? ''),
                ];
                $fileTanggal ??= $header['tanggal'];
                continue;
            }

            if ($tipe === '2' && $header !== null) {
                $rows[] = [
                    'unit_usaha_code' => $unitUsahaCode,
                    'tanggal'         => $header['tanggal'],
                    'no_voucher'      => $header['no_voucher'],
                    'no_urut'         => trim($parts[13] ?? ''),
                    'kode_akun'       => trim($parts[3] ?? ''),
                    'nama_akun'       => trim($parts[6] ?? ''),
                    'nominal'         => $this->toDecimal($parts[8] ?? '0'),
                    'nama_supplier'   => $header['nama_supplier'],
                    'keterangan'      => $header['keterangan'],
                ];
            }
        }

        return new ParsedFile(
            jenis: $this->jenis(),
            unitUsahaCode: $unitUsahaCode,
            tanggal: $fileTanggal,
            sourceHash: $sourceHash !== '' ? $sourceHash : sha1($content),
            rows: ['analisa_rkk_transactions' => $rows],
        );
    }

    private function toDecimal(string $v): float
    {
        $v = trim($v);
        return $v === '' || $v === '-' ? 0.0 : (float) $v;
    }
}
