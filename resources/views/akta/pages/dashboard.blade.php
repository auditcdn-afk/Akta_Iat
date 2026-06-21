@extends('akta.layouts.app')

@section('title', 'Dashboard - AKTA IAT')
@section('page_title', 'Dashboard')
@section('page_description', 'Ringkasan awal aplikasi audit')

@section('content')
<section class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-sm text-slate-400">Status Auth</p>
        <p id="dashboardAuthStatus" class="mt-2 text-2xl font-bold text-emerald-400">Memeriksa...</p>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-sm text-slate-400">API</p>
        <p id="dashboardApiStatus" class="mt-2 text-2xl font-bold text-blue-400">Online</p>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-sm text-slate-400">Data Store</p>
        <p id="dashboardDataStoreStatus" class="mt-2 text-2xl font-bold text-violet-400">Memuat...</p>
    </div>
</section>

<section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900 p-5">
    <h2 class="text-lg font-bold">Status Migrasi</h2>
    <p class="mt-2 text-sm text-slate-400">
        Auth Sanctum, sessionStorage, API ping, dan app_data sudah aktif. Selanjutnya setiap menu akan diisi modulnya
        satu per satu.
    </p>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-slate-950 p-4">
            <div class="text-xs text-slate-500">Backend</div>
            <div class="mt-1 font-semibold text-emerald-400">Laravel 13</div>
        </div>

        <div class="rounded-xl bg-slate-950 p-4">
            <div class="text-xs text-slate-500">Auth</div>
            <div class="mt-1 font-semibold text-emerald-400">Sanctum Token</div>
        </div>

        <div class="rounded-xl bg-slate-950 p-4">
            <div class="text-xs text-slate-500">Database</div>
            <div class="mt-1 font-semibold text-emerald-400">MySQL JSON</div>
        </div>

        <div class="rounded-xl bg-slate-950 p-4">
            <div class="text-xs text-slate-500">Frontend</div>
            <div class="mt-1 font-semibold text-emerald-400">Blade + Vanilla JS</div>
        </div>
    </div>
</section>
@endsection