<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

class SkMemutuskanExtractor
{
    /**
     * Ekstrak poin-poin "Memutuskan" dari file PDF SK.
     * Mengambil teks mulai dari heading "Memutuskan" sampai akhir dokumen
     * (atau sampai heading berikutnya yang bukan bagian dari daftar bernomor).
     */
    public static function extractFromPath(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
        } catch (Throwable) {
            return null;
        }

        return static::extractFromText($text);
    }

    public static function extractFromText(string $text): ?string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Heading "Memutuskan" tidak selalu diikuti baris baru pada hasil ekstraksi PDF
        // (kadang isi poin pertama langsung menyambung di baris yang sama), jadi cukup
        // cocokkan kata "Memutuskan" + tanda baca opsional, tanpa mewajibkan \n setelahnya.
        $found = preg_match('/Memutuskan\s*:?\s*/isu', $text, $matches, PREG_OFFSET_CAPTURE);

        // Beberapa PDF (hasil export tertentu) mengekstrak teks dengan setiap huruf
        // terpisah spasi (mis. "M e m u t u s k a n"), sehingga kata utuhnya tidak
        // pernah cocok. Coba gabungkan kembali huruf yang terpisah spasi sebagai fallback.
        if (!$found) {
            $text = static::despaceLetters($text);
            $found = preg_match('/Memutuskan\s*:?\s*/isu', $text, $matches, PREG_OFFSET_CAPTURE);
        }

        if (!$found) {
            return null;
        }

        // Offset dari PREG_OFFSET_CAPTURE dalam satuan byte, jadi pakai substr (byte-safe)
        // untuk memotong, baru diproses lebih lanjut dengan fungsi mb_*.
        $startPos = $matches[0][1] + strlen($matches[0][0]);
        $body = trim(substr($text, $startPos));

        if ($body === '') {
            return null;
        }

        // Hentikan di heading penutup umum (tanda tangan, tembusan, dll) jika ada.
        $stopWords = ['Ditetapkan di', 'Demikian', 'Tembusan', 'Yang Menetapkan'];
        foreach ($stopWords as $stop) {
            $pos = mb_stripos($body, $stop);
            if ($pos !== false && $pos > 20) {
                $body = trim(mb_substr($body, 0, $pos));
            }
        }

        $lines = preg_split('/\n+/', $body);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($l) => $l !== '');

        $cleaned = implode("\n", $lines);

        return $cleaned !== '' ? mb_substr($cleaned, 0, 5000) : null;
    }

    // Gabungkan kembali huruf-huruf yang terpisah spasi tunggal (mis. "M e m u t u s k a n"
    // menjadi "Memutuskan"), artefak umum dari beberapa PDF generator saat teks diekstrak.
    // Hanya menyasar rangkaian >=3 huruf tunggal berturut-turut agar kata pendek yang
    // memang wajar (mis. "di", "ke") tidak ikut termakan.
    private static function despaceLetters(string $text): string
    {
        return preg_replace_callback(
            '/(?:\p{L}[ \t]){2,}\p{L}\b/u',
            fn($m) => str_replace([' ', "\t"], '', $m[0]),
            $text
        );
    }
}
