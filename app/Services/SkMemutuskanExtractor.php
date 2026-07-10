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
        if (!preg_match('/Memutuskan\s*:?\s*/isu', $text, $matches, PREG_OFFSET_CAPTURE)) {
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
}
