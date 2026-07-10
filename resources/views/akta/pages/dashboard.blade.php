@extends('akta.layouts.app')

@section('title', 'Dashboard - SIMPAS-IAT')
@section('page_title', 'Dashboard')
@section('page_description', 'Ringkasan awal aplikasi audit')

@push('scripts')
    @vite('resources/js/akta-dashboard-audit-mandiri.js')
@endpush

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

<section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900 p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Pencapaian Audit Mandiri</h2>
            <p class="mt-1 text-sm text-slate-400">
                Realisasi vs target per jenis audit (KAS, SMH, Sparepart, BPKB, MT), dibedakan unit usaha H1 &amp; H2.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <select id="amdBulanFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></select>
            <select id="amdTahunFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></select>
        </div>
    </div>

    <div id="amdAlert" class="mt-4 hidden rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300"></div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <canvas id="amdChart" height="220"></canvas>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Unit</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Target</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Realisasi</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Capaian</th>
                        </tr>
                    </thead>
                    <tbody id="amdTableBody" class="divide-y divide-slate-800 text-slate-200">
                        <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection