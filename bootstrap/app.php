<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'akta.role' => \App\Http\Middleware\EnsureAktaRole::class,
            'akta.analisa-zona' => \App\Http\Middleware\EnsureAnalisaZonaAccess::class,
        ]);

        // Ke mana pengunjung yang belum login diarahkan. Tanpa ini Laravel
        // memakai rute bernama 'login' bawaan Breeze — yang sudah dihapus karena
        // aplikasi punya halaman login sendiri di /akta/login. Request API yang
        // mengirim header Accept: application/json tetap dapat balasan 401,
        // bukan pengalihan; ini hanya berlaku untuk kunjungan lewat browser.
        $middleware->redirectGuestsTo(fn() => route('akta.login'));

        // Percayai reverse proxy/load balancer (nginx, Cloudflare, dll) di hosting
        // produksi supaya HTTPS, IP klien, dan URL yang dihasilkan Laravel benar.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
