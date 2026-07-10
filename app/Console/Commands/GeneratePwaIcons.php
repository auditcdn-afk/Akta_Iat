<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';

    protected $description = 'Buat ikon PWA (192x192, 512x512, maskable, apple-touch-icon) dari public/images/logo.png. Kalau logo belum ada, dibuat ikon placeholder huruf "S".';

    // Warna tema aplikasi (biru SIMPAS-IAT).
    private const BG_COLOR = [37, 99, 235];

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('Ekstensi PHP GD tidak aktif. Aktifkan dulu ekstensi gd di php.ini.');
            return self::FAILURE;
        }

        $iconsDir = public_path('icons');
        if (!is_dir($iconsDir)) {
            mkdir($iconsDir, 0755, true);
        }

        $logoPath = public_path('images/logo.png');
        $source = null;

        if (file_exists($logoPath)) {
            $info = @getimagesize($logoPath);
            if ($info) {
                $source = match ($info['mime']) {
                    'image/png' => imagecreatefrompng($logoPath),
                    'image/jpeg' => imagecreatefromjpeg($logoPath),
                    'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($logoPath) : null,
                    default => null,
                };
            }
        }

        $sizes = [192, 512];
        foreach ($sizes as $size) {
            $this->makeIcon($source, $size, $iconsDir . "/icon-{$size}.png", padding: 0.12);
            $this->info("icon-{$size}.png dibuat.");
        }

        // Maskable: logo lebih kecil di tengah dengan padding aman (safe zone Android).
        $this->makeIcon($source, 512, $iconsDir . '/icon-maskable-512.png', padding: 0.22);
        $this->info('icon-maskable-512.png dibuat.');

        // Apple touch icon (iOS tidak transparan, background solid).
        $this->makeIcon($source, 180, $iconsDir . '/apple-touch-icon.png', padding: 0.14, transparent: false);
        $this->info('apple-touch-icon.png dibuat.');

        if ($source) {
            $this->info('Ikon dibuat dari public/images/logo.png.');
        } else {
            $this->warn('public/images/logo.png belum ditemukan — ikon placeholder huruf "S" yang dipakai. Taruh logo asli lalu jalankan ulang perintah ini.');
        }

        return self::SUCCESS;
    }

    private function makeIcon($source, int $size, string $outPath, float $padding = 0.12, bool $transparent = true): void
    {
        $canvas = imagecreatetruecolor($size, $size);

        if ($transparent) {
            imagesavealpha($canvas, true);
            $bg = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $bg);
        }

        $bgColor = imagecolorallocate($canvas, ...self::BG_COLOR);

        // Latar rounded-square sederhana (kotak penuh + sudut sedikit dipotong via arc).
        imagefilledrectangle($canvas, 0, 0, $size, $size, $bgColor);

        $inner = (int) round($size * (1 - $padding * 2));
        $offset = (int) round(($size - $inner) / 2);

        if ($source) {
            $srcW = imagesx($source);
            $srcH = imagesy($source);
            imagecopyresampled($canvas, $source, $offset, $offset, 0, 0, $inner, $inner, $srcW, $srcH);
        } else {
            // Placeholder: huruf "S" putih di tengah.
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $text = 'S';
            $fontPath = $this->builtinFontPath();

            if ($fontPath !== '') {
                $fontSize = (int) round($size * 0.42);
                $box = imagettfbbox($fontSize, 0, $fontPath, $text);
                $textW = abs($box[2] - $box[0]);
                $textH = abs($box[5] - $box[3]);
                $x = (int) (($size - $textW) / 2);
                $y = (int) (($size + $textH) / 2);
                imagettftext($canvas, $fontSize, 0, $x, $y, $white, $fontPath, $text);
            } else {
                $font = 5;
                $charW = imagefontwidth($font);
                $charH = imagefontheight($font);
                $x = (int) (($size - $charW) / 2);
                $y = (int) (($size - $charH) / 2);
                imagestring($canvas, $font, $x, $y, $text, $white);
            }
        }

        imagepng($canvas, $outPath);
        imagedestroy($canvas);
        if ($source) {
            // Jangan destroy $source di sini karena dipakai ulang untuk ukuran lain.
        }
    }

    private function builtinFontPath(): string
    {
        // Font TTF bawaan sistem Linux yang umum tersedia; fallback tidak dipakai jika tidak ada.
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }
}
