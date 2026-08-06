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

        return response(Artisan::output(), 200)->header('Content-Type', 'text/plain');
    }

    public function refreshReportAudit(Request $request, ReportAuditFlattener $flattener): Response
    {
        $this->checkToken($request);

        $count = $flattener->refreshAll();

        return response("OK - {$count} plan audit diproses pada " . now()->toDateTimeString(), 200)
            ->header('Content-Type', 'text/plain');
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

        return response($output, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Bangun ulang cache config, route, dan view.
     *
     * Tiap langkah dibungkus try/catch: kalau salah satu gagal (misalnya
     * direktori bootstrap/cache tidak bisa ditulis di hosting tertentu),
     * aplikasi tetap jalan — hanya kembali ke mode tanpa cache yang lebih
     * lambat — dan pesan kegagalannya ikut ditampilkan supaya bisa ditindak.
     */
    private function rebuildCaches(): string
    {
        $output = "\n--- Membangun ulang cache ---\n";

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            try {
                Artisan::call($command);
                $output .= trim(Artisan::output()) . "\n";
            } catch (\Throwable $e) {
                $output .= "GAGAL {$command}: {$e->getMessage()}\n";
            }
        }

        return $output;
    }
}
