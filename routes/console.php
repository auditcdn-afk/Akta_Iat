<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan token API yang sudah kedaluwarsa setiap hari, supaya tabel
// personal_access_tokens tidak terus membesar tanpa batas selama
// bertahun-tahun pemakaian. Butuh cron `php artisan schedule:run` tiap
// menit di server (lihat crontab pada dokumentasi deploy Laravel).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
