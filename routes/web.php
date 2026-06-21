<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('akta.login');
});

Route::view('/akta/login', 'akta.login')->name('akta.login');

Route::prefix('akta')->name('akta.')->group(function () {
    Route::view('/dashboard', 'akta.pages.dashboard')->name('dashboard');

    Route::get('/database', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Database',
            'description' => 'Modul data SMH, stok onhand, perlengkapan, plafon, unit area, harga, hasil, dan luar fisik.',
        ]);
    })->name('database');

    Route::view('/plan-audit', 'akta.pages.plan-audit')->name('plan-audit');

    Route::view('/task', 'akta.pages.task')->name('task');

    Route::get('/audit-mandiri', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Audit Mandiri',
            'description' => 'Modul BU Performance, pengecekan audit mandiri, dan sertijab.',
        ]);
    })->name('audit-mandiri');

    Route::view('/rekomendasi', 'akta.pages.rekomendasi')->name('rekomendasi');

    Route::view('/pica', 'akta.pages.pica')->name('pica');

    Route::view('/sk', 'akta.pages.sk')->name('sk');

    Route::view('/report-audit', 'akta.pages.report-audit')->name('report-audit');
    Route::redirect('/audit', '/akta/audit-detail/kas')->name('audit');
    Route::view('/audit-detail/kas', 'akta.pages.audit-detail-kas')->name('audit-detail.kas');

    Route::get('/bu-performance', function () {
        return view('akta.pages.placeholder', [
            'title' => 'BU Performance',
            'description' => 'Modul performa business unit, indikator pengecekan, dan evaluasi hasil.',
        ]);
    })->name('bu-performance');

    Route::get('/pulsa', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Pulsa',
            'description' => 'Modul realisasi pulsa, pencatatan penggunaan, dan rekap data.',
        ]);
    })->name('pulsa');

    Route::get('/mobil-dinas', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Mobil Dinas',
            'description' => 'Modul peminjaman mobil dinas, jadwal, pemakai, dan status kendaraan.',
        ]);
    })->name('mobil-dinas');

    Route::view('/pengguna', 'akta.pages.users')->name('pengguna');

    Route::view('/monitoring', 'akta.pages.monitoring')->name('monitoring');

    Route::get('/pengaturan', function () {
        return view('akta.pages.placeholder', [
            'title' => 'Pengaturan',
            'description' => 'Modul konfigurasi aplikasi, preferensi tampilan, dan pengaturan umum.',
        ]);
    })->name('pengaturan');

    Route::view('/manajemen-menu', 'akta.pages.menu-management')->name('manajemen-menu');
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
