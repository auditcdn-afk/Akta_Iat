<?php

namespace App\Services\AnalisaZona\Parsers;

use App\Services\AnalisaZona\ParsedFile;

/**
 * Format .ACC (data konsumen + kontrak pembiayaan per unit usaha per hari):
 *   baris 1: "KODE_UNIT;TGL_AWAL;TGL_AKHIR;" (tidak ada baris hash terpisah
 *   seperti RKK/LPK, jadi hash dedup dihitung dari isi file)
 *   baris tipe "0": data master konsumen (NIK, alamat, HP — data pribadi)
 *   baris tipe "1": kontrak/transaksi pembiayaan
 *   baris tipe "F": daftar piutang konsumen yang BELUM lunas per tanggal
 *   laporan — di data produksi nyata ini justru bagian TERBESAR file
 *   (bisa >80% baris), dan paling relevan untuk skor risiko zona.
 *
 * Baris tipe lain yang diamati ada di data nyata (2, 3, 5, 6, 7 — tampaknya
 * data administratif serah-terima unit/pelunasan panjar/penerimaan kas,
 * saling redundan dengan tipe "1") SENGAJA belum diparse di versi ini
 * karena tidak menambah sinyal risiko baru dibanding tipe 0/1/F — bisa
 * ditambah parser barisnya sendiri nanti kalau ternyata dibutuhkan.
 *
 * PERINGATAN: beberapa kolom numerik di baris tipe "1"/"F" belum sepenuhnya
 * terverifikasi maknanya satu-satu (contoh sample terbatas). Field yang
 * disimpan di bawah ini adalah yang paling meyakinkan; `raw_line` disimpan
 * utuh supaya bisa diparse ulang kalau ada koreksi mapping kolom nanti.
 */
class AccParser implements AnalisaFileParserInterface
{
    public function jenis(): string
    {
        return 'acc';
    }

    public function supports(string $filename): bool
    {
        return str_ends_with(strtolower($filename), '.acc');
    }

    public function parse(string $filename, string $content): ParsedFile
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        $headerParts   = explode(';', $lines[0] ?? '');
        $unitUsahaCode = trim($headerParts[0] ?? '');
        $tanggalAwal   = trim($headerParts[1] ?? '');

        $consumers   = [];
        $contracts   = [];
        $receivables = [];

        for ($i = 1; $i < count($lines); $i++) {
            $parts = explode(';', $lines[$i]);
            $tipe  = trim($parts[0] ?? '');

            if ($tipe === 'F') {
                $nominal = $this->toDecimal($parts[10] ?? '0');
                if ($nominal === 0.0) {
                    $nominal = $this->toDecimal($parts[17] ?? '0');
                }
                $receivables[] = [
                    'unit_usaha_code'   => $unitUsahaCode,
                    'tanggal_laporan'   => trim($parts[2] ?? '') ?: $tanggalAwal,
                    'kode_konsumen'     => trim($parts[3] ?? ''),
                    'no_bukti'          => trim($parts[7] ?? ''),
                    'tanggal_transaksi' => $this->nullIfEmpty($parts[8] ?? ''),
                    'kode_sales'        => trim($parts[9] ?? ''),
                    'nominal'           => $nominal,
                    'raw_line'          => $lines[$i],
                ];
                continue;
            }

            if ($tipe === '0') {
                $consumers[] = [
                    'unit_usaha_code' => $unitUsahaCode,
                    'tanggal'         => trim($parts[24] ?? '') ?: $tanggalAwal,
                    'kode_konsumen'   => trim($parts[2] ?? ''),
                    'nama'            => trim($parts[5] ?? ''),
                    'no_hp'           => trim($parts[11] ?? ''),
                    'nik'             => trim($parts[17] ?? ''),
                    'tgl_lahir'       => $this->nullIfEmpty($parts[13] ?? ''),
                    'no_rangka'       => trim($parts[18] ?? ''),
                    'dusun'           => trim($parts[6] ?? ''),
                    'kecamatan'       => trim($parts[7] ?? ''),
                    'kabupaten'       => trim($parts[8] ?? ''),
                    'desa'            => trim($parts[9] ?? ''),
                    'kode_pos'        => trim($parts[10] ?? ''),
                    'kode_wilayah'    => trim($parts[20] ?? ''),
                    'raw_line'        => $lines[$i],
                ];
                continue;
            }

            if ($tipe === '1') {
                $contracts[] = [
                    'unit_usaha_code' => $unitUsahaCode,
                    'tanggal'         => trim($parts[3] ?? '') ?: $tanggalAwal,
                    'no_bukti'        => trim($parts[2] ?? ''),
                    'no_faktur'       => trim($parts[4] ?? ''),
                    'kode_konsumen'   => trim($parts[7] ?? ''),
                    'jenis'           => trim($parts[9] ?? ''),
                    'harga_otr'       => $this->toDecimal($parts[12] ?? '0'),
                    'dp'              => $this->toDecimal($parts[13] ?? '0'),
                    'bunga'           => $this->toDecimal($parts[23] ?? '0'),
                    'kode_sales'      => trim($parts[24] ?? ''),
                    'status_flag'     => trim($parts[25] ?? ''),
                    'status_kredit'   => trim($parts[26] ?? ''),
                    'cara_bayar'      => trim($parts[27] ?? ''),
                    'raw_line'        => $lines[$i],
                ];
            }
        }

        return new ParsedFile(
            jenis: $this->jenis(),
            unitUsahaCode: $unitUsahaCode,
            tanggal: $tanggalAwal,
            sourceHash: sha1($content),
            rows: [
                'analisa_acc_consumers'   => $consumers,
                'analisa_acc_contracts'   => $contracts,
                'analisa_acc_receivables' => $receivables,
            ],
        );
    }

    private function toDecimal(string $v): float
    {
        $v = trim($v);
        return $v === '' || $v === '-' ? 0.0 : (float) $v;
    }

    private function nullIfEmpty(string $v): ?string
    {
        $v = trim($v);
        return ($v === '' || $v === '-' || $v === '0000-00-00') ? null : $v;
    }
}
