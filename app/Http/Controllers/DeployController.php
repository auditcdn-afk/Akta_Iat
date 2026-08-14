<?php

namespace App\Http\Controllers;

use App\Services\ReportAuditFlattener;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

// Endpoint darurat untuk hosting yang HANYA menyediakan FTP — tanpa SSH,
// tanpa menu Cron Jobs, tanpa phpMyAdmin — sehingga `php artisan migrate`
// dan penjadwalan refresh tidak bisa dijalankan lewat cara normal.
//
// Dilindungi oleh DEPLOY_SECRET di .env (bukan otentikasi user biasa),
// supaya bisa dipicu lewat URL browser atau layanan cron eksternal gratis
// (cron-job.org, EasyCron, dll) yang "mengetuk" URL ini secara berkala.
//
// PENTING (baca sebelum pakai):
//   1. Isi DEPLOY_SECRET di .env server dengan string acak yang panjang
//      (bukan kata yang mudah ditebak) sebelum route ini dipakai.
//   2. Endpoint /deploy/migrate hanya perlu dipanggil SEKALI setelah upload
//      awal / tiap kali ada migration baru — bukan untuk dijadwalkan cron.
//   3. Endpoint /deploy/refresh-report-audit boleh didaftarkan ke cron
//      eksternal untuk jalan tiap 2 jam (menggantikan `schedule:run`).
//   4. Kalau hosting Anda ternyata punya SSH/Cron Jobs, pakai cara normal
//      (`php artisan migrate`, crontab) dan JANGAN pakai controller ini —
//      lebih aman. File ini murni jalan darurat untuk FTP-only.
class DeployController extends Controller
{
    private function checkToken(Request $request): void
    {
        $secret = config('app.deploy_secret');

        abort_if(blank($secret), 404);
        abort_unless(hash_equals((string) $secret, (string) $request->query('token')), 403, 'Token salah.');
    }

    public function migrate(Request $request): Response
    {
        $this->checkToken($request);

        Artisan::call('migrate', ['--force' => true]);

        return response(Artisan::output(), 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function refreshReportAudit(Request $request, ReportAuditFlattener $flattener): Response
    {
        $this->checkToken($request);

        $count = $flattener->refreshAll();

        return response("OK - {$count} plan audit diproses pada " . now()->toDateTimeString(), 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    // Bersihkan cache view/config/route lewat browser — dipakai setelah upload
    // FTP supaya blade/PHP yang sudah diganti langsung kepakai, tanpa perlu
    // `php artisan view:clear` yang tidak bisa dijalankan tanpa SSH.
    //
    // Setelah dibersihkan, cache config, route, dan view langsung DIBANGUN
    // ULANG. Tanpa ini aplikasi selamanya berjalan tanpa cache: seluruh berkas
    // config dan routes/api.php (ratusan baris, puluhan import controller)
    // harus di-parse ulang pada setiap request — dan di hosting FTP-only tidak
    // ada cara lain menjalankan `php artisan optimize`.
    public function clearCache(Request $request): Response
    {
        $this->checkToken($request);

        Artisan::call('view:clear');
        $output = Artisan::output();

        Artisan::call('config:clear');
        $output .= Artisan::output();

        Artisan::call('route:clear');
        $output .= Artisan::output();

        Artisan::call('cache:clear');
        $output .= Artisan::output();

        $output .= $this->clearPackageManifest();

        $output .= $this->rebuildCaches();

        // artisan cache/view/config clears only clear Laravel's own caches — they
        // don't touch PHP's opcode cache. On hosts with OPcache enabled, an FTP
        // upload of a changed .php file (controllers, models, etc.) can keep
        // running the old compiled bytecode until OPcache re-checks the file on
        // its own schedule. Reset it here too so a PHP file change takes effect
        // immediately after hitting this endpoint, not just Blade view changes.
        if (function_exists('opcache_reset')) {
            $output .= opcache_reset() ? "OPcache: reset.\n" : "OPcache: reset gagal.\n";
        } else {
            $output .= "OPcache: tidak aktif di server ini, dilewati.\n";
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Bangun ulang cache config, route, dan view.
     *
     * Hasil tiap langkah dilaporkan berdasarkan ADA/TIDAKNYA berkas cache di
     * disk, bukan dari Artisan::output(). Alasannya: `config:cache` dan
     * `route:cache` mem-bootstrap instance aplikasi baru untuk membaca
     * konfigurasi/rute yang segar, sehingga pesan suksesnya masuk ke buffer
     * output aplikasi baru itu dan buffer pemanggil tetap kosong — padahal
     * berkasnya sebenarnya berhasil ditulis. Mengandalkan output di sini akan
     * membuat endpoint ini melaporkan "kosong" pada kondisi yang justru sukses.
     *
     * Tiap langkah dibungkus try/catch: kalau salah satu gagal (misalnya
     * direktori bootstrap/cache tidak bisa ditulis di hosting tertentu),
     * aplikasi tetap jalan — hanya kembali ke mode tanpa cache yang lebih
     * lambat — dan pesan kegagalannya ikut ditampilkan supaya bisa ditindak.
     */
    /**
     * Hapus daftar paket hasil auto-discovery Laravel
     * (bootstrap/cache/packages.php & services.php).
     *
     * Kedua berkas ini hanya ditulis ulang oleh `composer install/update` — bukan
     * oleh perintah artisan clear mana pun. Di hosting FTP-only composer tidak
     * pernah dijalankan, jadi isinya bisa tertinggal menyebut paket yang sudah
     * tidak ada lagi di vendor/. Menghapusnya di sini aman: Laravel membangun
     * ulang daftar itu otomatis pada request berikutnya, berdasarkan isi vendor/
     * yang sebenarnya.
     *
     * BATASNYA — dan ini penting saat menghapus paket composer: daftar paket
     * dibaca saat framework bootstrap, JAUH sebelum routing. Kalau vendor/ sudah
     * kehilangan sebuah paket sementara berkas ini masih menyebutnya, seluruh
     * aplikasi mati dengan "Class ... ServiceProvider not found" — termasuk
     * endpoint ini sendiri, sehingga tidak bisa dipakai untuk memulihkannya.
     * Satu-satunya jalan keluar saat itu adalah menghapus kedua berkas ini
     * lewat FTP. Karena itu: JANGAN menghapus isi vendor/ lewat FTP. Pembersihan
     * berkas ini di sini bersifat pencegahan (dipanggil selagi aplikasi masih
     * hidup), bukan penyelamat setelah terlanjur rusak.
     */
    private function clearPackageManifest(): string
    {
        $output = '';

        foreach (['packages.php', 'services.php'] as $berkas) {
            $path = base_path('bootstrap/cache/' . $berkas);

            if (!file_exists($path)) {
                $output .= "{$berkas}: tidak ada, dilewati.\n";
                continue;
            }

            $output .= @unlink($path)
                ? "{$berkas}: dihapus, akan dibangun ulang otomatis.\n"
                : "{$berkas}: GAGAL dihapus — periksa izin folder bootstrap/cache.\n";
        }

        return $output;
    }

    private function rebuildCaches(): string
    {
        $app = app();

        // Berkas yang harus ada setelah tiap perintah sukses. View tidak punya
        // satu berkas penanda, jadi cukup dipakai output Artisan-nya.
        $artifacts = [
            'config:cache' => $app->getCachedConfigPath(),
            'route:cache'  => $app->getCachedRoutesPath(),
            'view:cache'   => null,
        ];

        $output = "\n--- Membangun ulang cache ---\n";

        foreach ($artifacts as $command => $artifact) {
            try {
                Artisan::call($command);

                if ($artifact === null) {
                    $output .= trim(Artisan::output()) . "\n";
                    continue;
                }

                clearstatcache(true, $artifact);

                $output .= file_exists($artifact)
                    ? sprintf("%s: OK (%s, %d byte)\n", $command, basename($artifact), filesize($artifact))
                    : sprintf("%s: GAGAL — %s tidak terbentuk.\n", $command, basename($artifact));
            } catch (\Throwable $e) {
                $output .= "GAGAL {$command}: {$e->getMessage()}\n";
            }
        }

        return $output;
    }
}
