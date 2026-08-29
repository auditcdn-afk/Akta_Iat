<?php

namespace App\Services\AnalisaZona\Parsers;

use App\Services\AnalisaZona\AnalisaZonaImportService;
use App\Services\AnalisaZona\ParsedFile;
use Smalot\PdfParser\Parser as PdfTextParser;
use Throwable;

/**
 * Format LHPBK ("Laporan Harian Posisi Bank dan Kas") — beda dari
 * RKK/ACC/LPK, ini file PDF (bukan teks berpisah titik-koma), jadi
 * ekstraksi lewat smalot/pdfparser (dipakai juga oleh SkMemutuskanExtractor
 * untuk kasus lain di aplikasi ini).
 *
 * Isinya rekonsiliasi kas harian cabang: saldo awal, semua penerimaan &
 * pengeluaran, sampai saldo akhir kas & bank. RKK cuma mencatat voucher kas
 * kecil (biaya-biaya) — file ini mencatat POSISI KAS SEBENARNYA, termasuk
 * `saldo_akhir_kas` (kas yang masih dipegang cabang, belum disetor bank) —
 * indikator yang sebelumnya sama sekali tidak ada sinyalnya di skor zona.
 *
 * smalot/pdfparser menghapus spasi DI DALAM label (mis. "Saldo Pada Bank"
 * jadi "SaldoPadaBank") tapi tetap memisahkan label dari angkanya dengan
 * spasi — jadi label-label kunci dicocokkan sebagai prefix baris persis,
 * bukan regex fleksibel. Field yang tidak ketemu polanya dibiarkan 0 (bukan
 * bikin parse gagal total) — laporan ini formatnya cukup stabil (dicetak
 * dari sistem sumber yang sama di semua cabang), tapi kalau ada varian
 * layout, `raw_text` disimpan utuh sebagai jaring pengaman.
 */
class LhpbkParser implements AnalisaFileParserInterface
{
    public function jenis(): string
    {
        return 'lhpbk';
    }

    public function supports(string $filename): bool
    {
        $lower = strtolower($filename);
        return str_ends_with($lower, '.pdf') && str_contains($lower, 'lhpbk');
    }

    public function parse(string $filename, string $content): ParsedFile
    {
        $text = $this->extractText($content);
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $text));

        [$tanggal, $kodeRaw] = $this->cariTanggalDanKode($lines);
        $unitUsahaCode = AnalisaZonaImportService::normalizeUnitUsahaCode($kodeRaw);

        $saldoAwalBank  = $this->cariNominal($lines, 'SaldoPadaBank');
        $saldoAkhirBank = $this->cariNominal($lines, 'SaldoAkhirBank');
        $saldoAwalKas   = ($this->cariNominal($lines, 'SaldoPadaKasUangTunai') ?? 0.0)
            + ($this->cariNominal($lines, 'SaldoPadaKasGiroMundur') ?? 0.0);
        $saldoAkhirKas  = $this->cariNominal($lines, 'SaldoAkhirKas');

        // Fallback kalau baris "SaldoAkhirKas" gabungannya tidak ketemu —
        // jumlahkan dari baris rincian tunai + giro mundur.
        if ($saldoAkhirKas === null) {
            $saldoAkhirKas = ($this->cariNominal($lines, 'RincianSaldoAkhirKasUangTunai') ?? 0.0)
                + ($this->cariNominal($lines, 'RincianSaldoAkhirKasGiroMundur') ?? 0.0);
        }

        // Dua angka untuk rekonsiliasi silang dengan LPK & RKK — dicari lewat
        // KODE AKUN ("21011.", "22013."), bukan teks labelnya, karena kode
        // akun berasal dari bagan akun standar yang sama di semua cabang
        // sedangkan teks labelnya bisa berbeda penulisan.
        $penerimaanUnit = $this->cariNominal($lines, '21011.') ?? 0.0;
        $kasbon         = $this->cariNominal($lines, '22013.') ?? 0.0;

        return new ParsedFile(
            jenis: $this->jenis(),
            unitUsahaCode: $unitUsahaCode,
            tanggal: $tanggal,
            sourceHash: sha1($content),
            rows: [
                'analisa_posisi_kas' => [[
                    'unit_usaha_code'        => $unitUsahaCode,
                    'tanggal'                => $tanggal,
                    'saldo_awal_bank'        => $saldoAwalBank ?? 0.0,
                    'saldo_akhir_bank'       => $saldoAkhirBank ?? 0.0,
                    'saldo_awal_kas'         => $saldoAwalKas,
                    'saldo_akhir_kas'        => $saldoAkhirKas ?? 0.0,
                    'penerimaan_unit_tunai'  => $penerimaanUnit,
                    'penggantian_kasbon'     => $kasbon,
                    'penggantian_kasbon_ket' => $this->cariKeteranganSetelah($lines, '22013.'),
                    'raw_text'               => mb_substr($text, 0, 8000),
                ]],
            ],
        );
    }

    /**
     * Keterangan sebuah pos LHPBK ditulis di baris SESUDAH baris nominalnya
     * (mis. di bawah "22013.KAS-Penggantianuntukkasbon 8.853.300" ada
     * "ViaBPKNo0115/TDB/VIII/2026s&d0123/TDB/VIII/2026" — rentang nomor
     * voucher RKK yang diganti). Disimpan apa adanya untuk ditampilkan ke
     * auditor saat rekonsiliasinya selisih, supaya langsung kelihatan
     * voucher mana yang harus dicek.
     */
    private function cariKeteranganSetelah(array $lines, string $label): ?string
    {
        foreach ($lines as $i => $line) {
            if (!str_starts_with(preg_replace('/\s+/u', '', $line), $label)) {
                continue;
            }
            $berikutnya = trim($lines[$i + 1] ?? '');
            // Baris berikutnya belum tentu keterangan — bisa saja langsung pos
            // berikutnya (diawali kode akun) atau garis pemisah/subtotal.
            if ($berikutnya === '' || $berikutnya === '-' || preg_match('/^[\d.]+$/', $berikutnya) || preg_match('/^-{5,}/', $berikutnya) || preg_match('/^\d{5}\./', preg_replace('/\s+/u', '', $berikutnya))) {
                return null;
            }
            return mb_substr($berikutnya, 0, 255);
        }
        return null;
    }

    private function extractText(string $content): string
    {
        try {
            $pdf = (new PdfTextParser())->parseContent($content);
            return $pdf->getText();
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array{0: ?string, 1: string} [tanggal (Y-m-d), kode cabang mentah] */
    private function cariTanggalDanKode(array $lines): array
    {
        foreach ($lines as $line) {
            // Dicocokkan dari versi baris TANPA spasi sama sekali — smalot/
            // pdfparser menghapus spasi di dalam label pada sampel nyata
            // ("Per tanggal" jadi "Pertanggal"), tapi tidak semua PDF
            // generator berperilaku sama, jadi label dicocokkan longgar
            // (abaikan spasi) sementara tanggal & isi kurung tetap diambil
            // dari baris ASLI (pola angka/kurungnya sudah unik, tidak
            // terpengaruh spasi di sekitarnya).
            if (!str_starts_with(preg_replace('/\s+/u', '', $line), 'Pertanggal')) {
                continue;
            }
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $line, $mTgl) && preg_match('/\(([^)]+)\)/', $line, $mKode)) {
                return [$mTgl[1], trim($mKode[1])];
            }
        }
        return [null, ''];
    }

    /**
     * Cocokkan baris yang (setelah spasi dibuang) PERSIS diawali label
     * tertentu — bukan substring di tengah baris, supaya "SaldoAkhirKas"
     * tidak ikut kecocok pada baris "RincianSaldoAkhirKasUangTunai" (beda
     * label, kebetulan mengandung kata yang sama). Label dicocokkan dari
     * versi baris tanpa spasi (lihat catatan di cariTanggalDanKode) supaya
     * tetap kena baik format "SaldoPadaBank 0" maupun "Saldo Pada Bank 0".
     * Baris yang labelnya ketemu tapi tidak ada angka di ekornya (format
     * rusak/beda versi) dianggap tidak ketemu → null, bukan 0, supaya
     * pemanggil bisa bedakan "memang nol" vs "tidak ketemu sama sekali"
     * untuk fallback.
     */
    private function cariNominal(array $lines, string $label): ?float
    {
        foreach ($lines as $line) {
            if (!str_starts_with(preg_replace('/\s+/u', '', $line), $label)) {
                continue;
            }
            if (preg_match('/(-|[\d.]+)\s*$/u', trim($line), $m)) {
                return $this->toDecimal($m[1]);
            }
            return null;
        }
        return null;
    }

    private function toDecimal(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') {
            return 0.0;
        }
        // Format Indonesia: "." pemisah ribuan, tidak ada desimal di laporan ini.
        $v = str_replace('.', '', $v);
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
