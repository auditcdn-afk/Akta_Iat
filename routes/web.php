<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('akta.login');
});

Route::view('/akta/login', 'akta.login')->name('akta.login');

// Jalan darurat untuk hosting FTP-only (tanpa SSH/cron) — lihat DeployController.
// Nonaktif secara default: DeployController menolak semua request selama
// DEPLOY_SECRET di .env kosong.
Route::get('/deploy/migrate', [\App\Http\Controllers\DeployController::class, 'migrate']);
Route::get('/deploy/refresh-report-audit', [\App\Http\Controllers\DeployController::class, 'refreshReportAudit']);

Route::prefix('akta')->name('akta.')->group(function () {
    Route::view('/dashboard', 'akta.pages.dashboard')->name('dashboard');

    Route::view('/database', 'akta.pages.database')->name('database');

    Route::view('/plan-audit', 'akta.pages.plan-audit')->name('plan-audit');

    Route::view('/task', 'akta.pages.task')->name('task');

    Route::view('/audit-mandiri', 'akta.pages.audit-mandiri')->name('audit-mandiri');

    Route::view('/rekomendasi', 'akta.pages.rekomendasi')->name('rekomendasi');

    Route::view('/pica', 'akta.pages.pica')->name('pica');

    Route::view('/sk', 'akta.pages.sk')->name('sk');

    Route::view('/report-audit', 'akta.pages.report-audit')->name('report-audit');
    Route::get('/report-audit/pdf/{plan}', [\App\Http\Controllers\ReportPdfController::class, 'show'])->name('report-audit.pdf');
    Route::get('/report-audit/pdf/{plan}/download', [\App\Http\Controllers\ReportPdfController::class, 'download'])->name('report-audit.pdf.download');
    Route::view('/audit', 'akta.pages.audit')->name('audit');
    Route::view('/grading', 'akta.pages.grading')->name('grading');
    Route::view('/audit-detail/kas', 'akta.pages.audit-detail-kas')->name('audit-detail.kas');

    Route::view('/bu-performance', 'akta.pages.bu-performance')->name('bu-performance');

    Route::view('/pulsa', 'akta.pages.pulsa')->name('pulsa');

    Route::view('/mobil-dinas', 'akta.pages.mobil-dinas')->name('mobil-dinas');

    Route::view('/realisasi-dinas', 'akta.pages.realisasi-dinas')->name('realisasi-dinas');

    Route::view('/pengguna', 'akta.pages.users')->name('pengguna');

    Route::view('/monitoring', 'akta.pages.monitoring')->name('monitoring');

    Route::get('/pengaturan', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Pengaturan',
            'description' => 'Modul konfigurasi aplikasi, preferensi tampilan, dan pengaturan umum.',
        ]);
    })->name('pengaturan');

    Route::view('/manajemen-menu', 'akta.pages.menu-management')->name('manajemen-menu');

    Route::view('/profile', 'akta.pages.profile')->name('profile');
});

/*
|--------------------------------------------------------------------------
| Breeze Routes
|--------------------------------------------------------------------------
*/

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__ . '/auth.php';
