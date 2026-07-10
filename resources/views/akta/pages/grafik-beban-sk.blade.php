@extends('akta.layouts.app')

@section('title', 'Grafik Beban SK - SIMPAS-IAT')
@section('page_title', 'Grafik Beban SK')
@section('page_description', 'Rekap beban SK audit per bulan, per unit usaha, dan status pembebanan')

@push('scripts')
    @vite('resources/js/akta-grafik-beban-sk.js')
@endpush

@section('content')
<section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Beban SK Audit</h2>
            <p class="mt-1 text-sm text-slate-400">Total nominal beban SK, dikelompokkan per bulan, unit usaha, dan status pembebanan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <button type="button" id="gbTahunFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="gbTahunFilterLabel">Semua Tahun</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="gbTahunFilterPanel" class="gb-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-36 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg"></div>
            </div>
            <div class="relative">
                <button type="button" id="gbBulanFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="gbBulanFilterLabel">Semua Bulan</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="gbBulanFilterPanel" class="gb-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-44 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg"></div>
            </div>
            <div class="relative">
                <button type="button" id="gbJenisUnitFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="gbJenisUnitFilterLabel">Semua Jenis Unit</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="gbJenisUnitFilterPanel" class="gb-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-40 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg"></div>
            </div>
            <div class="relative">
                <button type="button" id="gbStatusFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="gbStatusFilterLabel">Semua Status</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="gbStatusFilterPanel" class="gb-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-40 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg"></div>
            </div>
        </div>
    </div>

    <div id="gbAlert" class="mt-4 hidden rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300"></div>

    <div class="mt-5 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-blue-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Beban</p>
            <p id="gbStatTotal" class="mt-1 text-xl font-bold text-blue-300">Rp 0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-emerald-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Beban Final</p>
            <p id="gbStatFinal" class="mt-1 text-xl font-bold text-emerald-300">Rp 0</p>
            <p class="mt-1 text-[11px] text-slate-500"><span id="gbStatFinalCount">0</span> SK</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-amber-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Beban Draft</p>
            <p id="gbStatDraft" class="mt-1 text-xl font-bold text-amber-300">Rp 0</p>
            <p class="mt-1 text-[11px] text-slate-500"><span id="gbStatDraftCount">0</span> SK</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah Unit Usaha</p>
            <p id="gbStatUnitCount" class="mt-1 text-xl font-bold text-violet-300">0</p>
        </div>
    </div>

    <div class="mt-5 rounded-xl border border-slate-800 bg-slate-950 p-4">
        <h3 class="mb-3 text-sm font-bold text-slate-200">Tren Beban SK per Bulan</h3>
        <canvas id="gbTrendChart" height="220"></canvas>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Beban per Jenis Unit</h3>
            <canvas id="gbJenisUnitChart" height="220"></canvas>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-bold text-slate-200">Beban per Unit Usaha <span class="font-normal text-slate-500">(terbesar dulu)</span></h3>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="search" id="gbUnitSearch" placeholder="Cari unit usaha..."
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 py-2 pl-8 pr-3 text-sm text-slate-100 outline-none focus:border-blue-500 sm:w-52">
                </div>
            </div>
            <div class="akta-scrollbar max-h-[420px] overflow-y-auto">
                <div id="gbUnitChartWrap" class="relative">
                    <canvas id="gbUnitChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 overflow-hidden rounded-xl border border-slate-800">
        <div class="max-h-[420px] overflow-y-auto akta-scrollbar">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="sticky top-0 bg-slate-950/95">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Usaha</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Unit</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah SK</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Total Beban</th>
                    </tr>
                </thead>
                <tbody id="gbTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
