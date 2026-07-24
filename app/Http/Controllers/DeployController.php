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
}
