<?php

use Illuminate\Support\Facades\Route;

// Route::redirect / Route::view lebih ringan daripada closure saat rute di-cache
// (closure ikut ter-serialisasi ke bootstrap/cache) — lihat DeployController.
Route::redirect('/', '/akta/login');

Route::view('/akta/login', 'akta.login')->name('akta.login');

// Jalan darurat untuk hosting FTP-only (tanpa SSH/cron) — lihat DeployController.
// Nonaktif secara default: DeployController menolak semua request selama
// DEPLOY_SECRET di .env kosong.
Route::get('/deploy/migrate', [\App\Http\Controllers\DeployController::class, 'migrate']);
Route::get('/deploy/refresh-report-audit', [\App\Http\Controllers\DeployController::class, 'refreshReportAudit']);
Route::get('/deploy/clear-cache', [\App\Http\Controllers\DeployController::class, 'clearCache']);

Route::prefix('akta')->name('akta.')->group(function () {
    Route::view('/dashboard', 'akta.pages.dashboard')->name('dashboard');

    Route::view('/database', 'akta.pages.database')->name('database');

    Route::view('/plan-audit', 'akta.pages.plan-audit')->name('plan-audit');
    Route::get('/plan-audit/{plan}/spt', [\App\Http\Controllers\PlanAuditPdfController::class, 'spt'])->name('plan-audit.spt');

    Route::view('/task', 'akta.pages.task')->name('task');

    Route::view('/audit-mandiri', 'akta.pages.audit-mandiri')->name('audit-mandiri');

    Route::view('/rekomendasi', 'akta.pages.rekomendasi')->name('rekomendasi');

    Route::view('/pica', 'akta.pages.pica')->name('pica');

    Route::view('/sk', 'akta.pages.sk')->name('sk');

    Route::view('/report-audit', 'akta.pages.report-audit')->name('report-audit');
    Route::get('/report-audit/pdf/{plan}', [\App\Http\Controllers\ReportPdfController::class, 'show'])->name('report-audit.pdf');
    Route::get('/report-audit/pdf/{plan}/download', [\App\Http\Controllers\ReportPdfController::class, 'download'])->name('report-audit.pdf.download');
    Route::get('/report-audit/mt-rekap/{plan}', [\App\Http\Controllers\ReportPdfController::class, 'mtRekap'])->name('report-audit.mt-rekap');
    Route::view('/audit', 'akta.pages.audit')->name('audit');
    Route::view('/grading', 'akta.pages.grading')->name('grading');
    Route::view('/audit-detail/kas', 'akta.pages.audit-detail-kas')->name('audit-detail.kas');

    Route::view('/bu-performance', 'akta.pages.bu-performance')->name('bu-performance');

    Route::view('/pulsa', 'akta.pages.pulsa')->name('pulsa');

    Route::view('/mobil-dinas', 'akta.pages.mobil-dinas')->name('mobil-dinas');

    Route::view('/realisasi-dinas', 'akta.pages.realisasi-dinas')->name('realisasi-dinas');

    Route::view('/karyawan', 'akta.pages.karyawan')->name('karyawan');

    Route::view('/pengguna', 'akta.pages.users')->name('pengguna');

    Route::view('/monitoring', 'akta.pages.monitoring')->name('monitoring');

    Route::view('/pengaturan', 'akta.pages.placeholder', [
        'title' => 'Pengaturan',
        'description' => 'Modul konfigurasi aplikasi, preferensi tampilan, dan pengaturan umum.',
    ])->name('pengaturan');

    Route::view('/manajemen-menu', 'akta.pages.menu-management')->name('manajemen-menu');

    Route::view('/profile', 'akta.pages.profile')->name('profile');

    // Akses sebenarnya dijaga oleh middleware `akta.analisa-zona` di setiap
    // endpoint API-nya (lihat routes/api.php) — halaman ini sendiri boleh
    // dirender untuk siapa saja yang login, tapi tanpa flag itu semua
    // pemanggilan API-nya akan ditolak 403 dan halaman menampilkan pesan
    // akses ditolak (lihat akta-analisa-zona.js).
    Route::view('/analisa-zona', 'akta.pages.analisa-zona')->name('analisa-zona');

    // Upload harian RKK/ACC/LPK oleh unit usaha — terbuka untuk SEMUA user
    // login (bukan cuma tim analisa), lihat routes/api.php untuk endpointnya.
    Route::view('/upload-analisa', 'akta.pages.upload-analisa')->name('upload-analisa');
});

// Scaffolding Laravel Breeze (rute /login, /register, /forgot-password,
// /dashboard, /profile bawaan) sudah dihapus: aplikasi ini punya alur login
// sendiri di /akta/login dengan token Sanctum, dan halaman profilnya sendiri
// di /akta/profile. Tidak ada satu pun halaman akta yang memakainya.
// Pengalihan pengunjung yang belum login diatur di bootstrap/app.php.
