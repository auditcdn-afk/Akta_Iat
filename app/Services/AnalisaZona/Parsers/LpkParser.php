<?php

namespace App\Services\AnalisaZona\Parsers;

use App\Services\AnalisaZona\ParsedFile;

/**
 * Format .LPK (Laporan Penjualan unit & penerimaan Kwitansi Gantung per
 * unit usaha per hari):
 *   baris 1: hash sumber
 *   baris 2: "0;KODE_UNIT;TANGGAL;" (header, bukan baris transaksi)
 *   baris berikutnya: baris tipe "1" = 1 baris transaksi (penjualan unit
 *   baru / penerimaan kwitansi gantung, dibedakan lewat kolom kode
 *   transaksi PBBO/PBAR/CRGT/CC).
 *
 * Posisi kolom no_bukti & no_faktur BERGESER tergantung jenis transaksi
 * (baris PBBO vs CRGT tidak selalu punya jumlah field yang sama terisi) —
 * jadi keduanya diekstrak lewat pola regex dari baris mentah, bukan index
 * kolom tetap, supaya tetap benar walau posisinya bergeser.
 */
class LpkParser implements AnalisaFileParserInterface
{
    private const NO_BUKTI_PATTERN  = '/\bH\d{4,6}-\d{2,4}\b/';
    private const NO_FAKTUR_PATTERN = '/\b\d{3,4}\/[A-Z]{2,5}\/[IVXLCDM]+\/\d{4}\b/';

    public function jenis(): string
    {
        return 'lpk';
    }

    public function supports(string $filename): bool
    {
        return str_ends_with(strtolower($filename), '.lpk');
    }

    public function parse(string $filename, string $content): ParsedFile
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        $sourceHash = trim($lines[0] ?? '');
        $headerParts = explode(';', $lines[1] ?? '');
        $unitUsahaCode = trim($headerParts[1] ?? '');
        $tanggal       = trim($headerParts[2] ?? '');

        $rows = [];

        for ($i = 2; $i < count($lines); $i++) {
            $line  = $lines[$i];
            $parts = explode(';', $line);
            $tipe  = trim($parts[0] ?? '');

            if ($tipe !== '1') {
                continue;
            }

            $nominal = $this->toDecimal($parts[10] ?? '0');
            if ($nominal === 0.0) {
                $nominal = $this->toDecimal($parts[15] ?? '0');
            }

            preg_match(self::NO_BUKTI_PATTERN, $line, $mBukti);
            preg_match(self::NO_FAKTUR_PATTERN, $line, $mFaktur);

            $rows[] = [
                'unit_usaha_code' => $unitUsahaCode,
                'tanggal'         => $tanggal,
                'kode_urut'       => trim($parts[1] ?? ''),
                'kode_konsumen'   => trim($parts[3] ?? ''),
                'nama_konsumen'   => trim($parts[4] ?? ''),
                'kode_finance'    => trim($parts[5] ?? ''),
                'no_bukti'        => $mBukti[0] ?? null,
                'no_faktur'       => $mFaktur[0] ?? null,
                'nominal'         => $nominal,
                'kode_transaksi'  => trim($parts[16] ?? ''),
                'jenis_transaksi' => trim($parts[17] ?? ''),
                'status_flag'     => trim($parts[18] ?? ''),
                'keterangan'      => trim($parts[19] ?? '') ?: null,
                'raw_line'        => $line,
            ];
        }

        return new ParsedFile(
            jenis: $this->jenis(),
            unitUsahaCode: $unitUsahaCode,
            tanggal: $tanggal,
            sourceHash: $sourceHash !== '' ? $sourceHash : sha1($content),
            rows: ['analisa_lpk_penjualan' => $rows],
        );
    }

    private function toDecimal(string $v): float
    {
        $v = trim($v);
        return $v === '' || $v === '-' ? 0.0 : (float) $v;
    }
}
