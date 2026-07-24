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

// Bangun ulang tabel ringkasan report_audit_summaries + report_audit_checklist_items
// (sumber data Power BI & export Excel) tiap 2 jam — bukan dihitung on-the-fly saat
// dashboard/export dibuka, supaya query join+JSON yang berat tidak membebani aplikasi
// yang dipakai auditor sehari-hari. Sesuaikan interval bila butuh data lebih "segar".
Schedule::command('akta:refresh-report-audit-flat')->everyTwoHours()->withoutOverlapping();
