@extends('akta.layouts.app')

@section('title', 'Dashboard - SIMPAS-IAT')
@section('page_title', 'Dashboard')
@section('page_description', 'Ringkasan awal aplikasi audit')

@push('scripts')
    @vite(['resources/js/akta-dashboard-audit-mandiri.js', 'resources/js/akta-grafik-beban-sk.js'])
@endpush

@section('content')

{{-- Hero banner --}}
<section class="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-indigo-600 via-blue-600 to-teal-500 p-6 shadow-lg">
    <div class="pointer-events-none absolute -right-10 -top-10 h-56 w-56 rounded-full bg-white/10"></div>
    <div class="pointer-events-none absolute -bottom-16 right-24 h-40 w-40 rounded-full bg-white/10"></div>
    <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p id="dashboardGreeting" class="text-sm font-medium text-white/80">Selamat datang kembali 👋</p>
            <h1 id="dashboardUserName" class="mt-1 text-2xl font-bold text-white">SIMPAS-IAT</h1>
            <p class="mt-1 text-sm text-white/80">Sistem Informasi Manajemen Pengawasan Audit &amp; Standarisasi — Internal Audit Team</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                Auth <span id="dashboardAuthStatus">Memeriksa...</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-blue-200"></span>
                API <span id="dashboardApiStatus">Online</span>
            </span>
        </div>
    </div>
</section>

{{-- Akses cepat --}}
<section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('akta.plan-audit') }}" class="group rounded-2xl border border-slate-800 bg-gradient-to-br from-blue-500/15 to-transparent p-5 transition hover:border-blue-500/40 hover:-translate-y-0.5">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/20 text-2xl">📋</div>
        <div class="mt-3 font-bold text-slate-100 group-hover:text-blue-300">Plan Audit</div>
        <p class="mt-1 text-xs text-slate-400">Kelola rencana audit &amp; jadwal tim</p>
    </a>

    <a href="{{ route('akta.task') }}" class="group rounded-2xl border border-slate-800 bg-gradient-to-br from-amber-500/15 to-transparent p-5 transition hover:border-amber-500/40 hover:-translate-y-0.5">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/20 text-2xl">✅</div>
        <div class="mt-3 font-bold text-slate-100 group-hover:text-amber-300">Task</div>
        <p class="mt-1 text-xs text-slate-400">Pantau progres tugas audit tim</p>
    </a>

    <a href="{{ route('akta.report-audit') }}" class="group rounded-2xl border border-slate-800 bg-gradient-to-br from-emerald-500/15 to-transparent p-5 transition hover:border-emerald-500/40 hover:-translate-y-0.5">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/20 text-2xl">📊</div>
        <div class="mt-3 font-bold text-slate-100 group-hover:text-emerald-300">Report Audit</div>
        <p class="mt-1 text-xs text-slate-400">Lihat hasil &amp; cetak laporan audit</p>
    </a>

    <a href="{{ route('akta.mobil-dinas') }}" class="group rounded-2xl border border-slate-800 bg-gradient-to-br from-violet-500/15 to-transparent p-5 transition hover:border-violet-500/40 hover:-translate-y-0.5">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/20 text-2xl">🚗</div>
        <div class="mt-3 font-bold text-slate-100 group-hover:text-violet-300">Mobil Dinas</div>
        <p class="mt-1 text-xs text-slate-400">Ajukan &amp; pantau penggunaan mobil dinas</p>
    </a>
</section>

{{-- Tab switcher --}}
<div class="mt-6 flex gap-2 border-b border-slate-800">
    <button type="button" id="dashTabBtnAuditMandiri" data-dash-tab="auditMandiri"
        class="dashTabBtn border-b-2 border-blue-500 px-4 py-2 text-sm font-semibold text-blue-300">
        Pencapaian Audit Mandiri
    </button>
    <button type="button" id="dashTabBtnBebanSk" data-dash-tab="bebanSk"
        class="dashTabBtn border-b-2 border-transparent px-4 py-2 text-sm font-semibold text-slate-400 hover:text-slate-200">
        Grafik Beban SK
    </button>
</div>

<div id="dashTabAuditMandiri" class="dashTabPanel">
<section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900 p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Pencapaian Audit Mandiri</h2>
            <p class="mt-1 text-sm text-slate-400">
                Realisasi vs target per jenis audit (KAS, SMH, Sparepart, BPKB, MT), dibedakan unit usaha H1 &amp; H2.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <button type="button" id="amdWilayahFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="amdWilayahFilterLabel">Semua Wilayah</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="amdWilayahFilterPanel"
                    class="amd-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-48 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg">
                </div>
            </div>
            <div class="relative">
                <button type="button" id="amdJenisAuditFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="amdJenisAuditFilterLabel">Semua Jenis Audit</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="amdJenisAuditFilterPanel"
                    class="amd-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-44 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg">
                </div>
            </div>
            <div class="relative">
                <button type="button" id="amdBulanFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="amdBulanFilterLabel">Bulan</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="amdBulanFilterPanel"
                    class="amd-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-44 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg">
                </div>
            </div>
            <div class="relative">
                <button type="button" id="amdTahunFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="amdTahunFilterLabel">Tahun</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="amdTahunFilterPanel"
                    class="amd-filter-panel akta-scrollbar absolute right-0 z-20 mt-2 hidden max-h-64 w-36 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg">
                </div>
            </div>
        </div>
    </div>

    <div id="amdAlert" class="mt-4 hidden rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300"></div>

    <div class="mt-5 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-blue-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Target</p>
            <p id="amdStatTarget" class="mt-1 text-2xl font-bold text-blue-300">0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-emerald-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Realisasi</p>
            <p id="amdStatRealisasi" class="mt-1 text-2xl font-bold text-emerald-300">0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Achieve Rata-rata</p>
            <p id="amdStatCapaian" class="mt-1 text-2xl font-bold text-violet-300">0%</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-red-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Belum Audit</p>
            <p id="amdStatBelum" class="mt-1 text-2xl font-bold text-red-300">0</p>
        </div>
    </div>

    <div class="mt-3 grid gap-3 grid-cols-2 lg:grid-cols-4">
        <button type="button" id="amdCcCardOk" data-cc-status="ok"
            class="amdCcCard rounded-xl border border-slate-800 bg-slate-900 p-4 text-left transition hover:border-emerald-500/40 hover:bg-emerald-500/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cross-check OK</p>
            <p id="amdStatCcOk" class="mt-1 text-2xl font-bold text-emerald-300">0</p>
            <p class="mt-1 text-[11px] text-slate-500">Klik untuk lihat rincian</p>
        </button>
        <button type="button" id="amdCcCardNotOk" data-cc-status="notOk"
            class="amdCcCard rounded-xl border border-slate-800 bg-slate-900 p-4 text-left transition hover:border-red-500/40 hover:bg-red-500/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cross-check Not OK</p>
            <p id="amdStatCcNotOk" class="mt-1 text-2xl font-bold text-red-300">0</p>
            <p class="mt-1 text-[11px] text-slate-500">Klik untuk lihat rincian</p>
        </button>
        <button type="button" id="amdCcCardSelisih" data-cc-status="selisih"
            class="amdCcCard rounded-xl border border-slate-800 bg-slate-900 p-4 text-left transition hover:border-amber-500/40 hover:bg-amber-500/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cross-check Selisih</p>
            <p id="amdStatCcSelisih" class="mt-1 text-2xl font-bold text-amber-300">0</p>
            <p class="mt-1 text-[11px] text-slate-500">Klik untuk lihat rincian</p>
        </button>
        <button type="button" id="amdCcCardPending" data-cc-status="pending"
            class="amdCcCard rounded-xl border border-slate-800 bg-slate-900 p-4 text-left transition hover:border-slate-500/60 hover:bg-slate-800/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Belum Cross-check</p>
            <p id="amdStatCcPending" class="mt-1 text-2xl font-bold text-slate-400">0</p>
            <p class="mt-1 text-[11px] text-slate-500">Klik untuk lihat rincian</p>
        </button>
    </div>

    <div id="amdCcDetailPanel" class="mt-3 hidden rounded-xl border border-slate-800 bg-slate-950 p-4">
        <div class="flex items-center justify-between">
            <h3 id="amdCcDetailTitle" class="text-sm font-bold text-slate-200">Rincian Cross-check</h3>
            <button type="button" id="amdCcDetailCloseBtn" class="text-slate-500 hover:text-slate-300">✕</button>
        </div>
        <div class="mt-3 max-h-72 overflow-y-auto akta-scrollbar">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead>
                    <tr>
                        <th class="px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Usaha</th>
                        <th class="px-2 py-1.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Wilayah</th>
                        <th class="px-2 py-1.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Audit</th>
                        <th class="px-2 py-1.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah</th>
                    </tr>
                </thead>
                <tbody id="amdCcDetailBody" class="divide-y divide-slate-800 text-slate-200"></tbody>
            </table>
        </div>
    </div>

    <div class="mt-5 rounded-xl border border-slate-800 bg-slate-950 p-4">
        <h3 class="mb-3 text-sm font-bold text-slate-200">Ringkasan per Jenis Audit &amp; Jenis Unit</h3>
        <div class="mx-auto" style="max-width: 720px;">
            <canvas id="amdSummaryChart" height="240"></canvas>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-sm font-bold text-slate-200">Per Unit Usaha <span class="font-normal text-slate-500">(diurutkan dari capaian terendah)</span></h3>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="search" id="amdUnitSearch" placeholder="Cari unit usaha..."
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 py-2 pl-8 pr-3 text-sm text-slate-100 outline-none focus:border-blue-500 sm:w-52">
                </div>
            </div>
            <div class="akta-scrollbar max-h-[420px] overflow-y-auto">
                <div id="amdUnitChartWrap" class="relative">
                    <canvas id="amdUnitChart"></canvas>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <div class="max-h-[420px] overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="sticky top-0 bg-slate-950/95">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Usaha</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Wilayah</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Target</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Realisasi</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Achieve</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Cross-check</th>
                        </tr>
                    </thead>
                    <tbody id="amdTableBody" class="divide-y divide-slate-800 text-slate-200">
                        <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
</div>

<div id="dashTabBebanSk" class="dashTabPanel hidden">
<section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900 p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Beban SK Audit</h2>
            <p class="mt-1 text-sm text-slate-400">Total nominal beban SK, dikelompokkan per bulan, unit usaha, dan status pembebanan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <button type="button" id="gbUnitUsahaFilterBtn"
                    class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <span id="gbUnitUsahaFilterLabel">Semua Unit Usaha</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="gbUnitUsahaFilterPanel" class="gb-filter-panel akta-scrollbar absolute left-0 z-20 mt-2 hidden max-h-64 w-56 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-lg">
                    <input type="search" id="gbUnitUsahaFilterSearch" placeholder="Cari..."
                        class="mb-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
                    <div id="gbUnitUsahaFilterOptions"></div>
                </div>
            </div>
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

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Tren Beban SK per Bulan</h3>
            <canvas id="gbTrendChart" height="220"></canvas>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Nominal &amp; Jumlah Kasus per Tahun</h3>
            <canvas id="gbTahunChart" height="220"></canvas>
        </div>
    </div>

    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Beban per Jenis Unit</h3>
            <canvas id="gbJenisUnitChart" height="220"></canvas>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Nominal per Item Pembebanan</h3>
            <canvas id="gbItemChart" height="220"></canvas>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Beban per Jabatan</h3>
            <canvas id="gbJabatanChart" height="220"></canvas>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4">
            <h3 class="mb-3 text-sm font-bold text-slate-200">Beban per Personil <span class="font-normal text-slate-500">(top 15)</span></h3>
            <div class="akta-scrollbar max-h-[280px] overflow-y-auto">
                <div id="gbPersonilChartWrap" class="relative">
                    <canvas id="gbPersonilChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
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
            <div class="akta-scrollbar max-h-[360px] overflow-y-auto">
                <div id="gbUnitChartWrap" class="relative">
                    <canvas id="gbUnitChart"></canvas>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <div class="max-h-[360px] overflow-y-auto akta-scrollbar">
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
    </div>
</section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const buttons = document.querySelectorAll('.dashTabBtn');
        const panels = {
            auditMandiri: document.getElementById('dashTabAuditMandiri'),
            bebanSk: document.getElementById('dashTabBebanSk'),
        };
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.dashTab;
                buttons.forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('border-blue-500', active);
                    b.classList.toggle('text-blue-300', active);
                    b.classList.toggle('border-transparent', !active);
                    b.classList.toggle('text-slate-400', !active);
                });
                Object.entries(panels).forEach(([key, el]) => {
                    el?.classList.toggle('hidden', key !== tab);
                });
            });
        });
    });
</script>
@endsection