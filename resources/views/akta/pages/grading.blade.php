@extends('akta.layouts.app')

@section('title', 'Grading - SIMPAS-IAT')
@section('page_title', 'Grading')
@section('page_description', 'Analisa hasil grading audit per unit usaha')

@push('scripts')
    @vite('resources/js/akta-grading.js')
@endpush

@section('content')
<section class="space-y-5">

    {{-- Header & Filter --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Rekap Hasil Grading</h2>
            <p class="mt-1 text-sm text-slate-400">
                Data grading yang sudah selesai disimpan, dikelompokkan berdasarkan unit usaha dan no plan audit.
            </p>
        </div>
        <div class="flex flex-col gap-2 lg:flex-row">
            <input id="gradingSearch" type="search" placeholder="Cari cabang / no SPT / wilayah..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-72">
            <select id="gradingWilayahFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Wilayah</option>
            </select>
            <select id="gradingJenisFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Jenis</option>
                <option value="Cabang">Cabang</option>
                <option value="Bengkel">Bengkel</option>
                <option value="WHS PART">WHS PART</option>
                <option value="WHS UNIT">WHS UNIT</option>
            </select>
        </div>
    </div>

    {{-- Alert --}}
    <div id="gradingAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Grading</div>
            <div id="gStatTotal" class="mt-2 text-2xl font-bold text-slate-100">0</div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rata-rata Nilai</div>
            <div id="gStatAvg" class="mt-2 text-2xl font-bold text-blue-300">-</div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ada Fraud</div>
            <div id="gStatFraud" class="mt-2 text-2xl font-bold text-red-400">0</div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">BBNKB</div>
            <div id="gStatBbnkb" class="mt-2 text-2xl font-bold text-amber-300">0</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Unit Usaha / Cabang</th>
                        <th class="px-4 py-3 text-left">No SPT</th>
                        <th class="px-4 py-3 text-left">Wilayah</th>
                        <th class="px-4 py-3 text-left">Jenis</th>
                        <th class="px-4 py-3 text-left">Tgl Mulai</th>
                        <th class="px-4 py-3 text-left">Tgl Selesai</th>
                        <th class="px-4 py-3 text-center">Nilai</th>
                        <th class="px-4 py-3 text-center">Item</th>
                        <th class="px-4 py-3 text-center">Fraud</th>
                        <th class="px-4 py-3 text-center">BBNKB</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="gradingTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr>
                        <td colspan="11" class="py-12 text-center text-slate-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</section>

{{-- Detail / Analisa Modal --}}
<div id="gradingDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
    <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
        {{-- Modal Header --}}
        <div class="flex items-center justify-between border-b border-slate-700 px-5 py-4">
            <div>
                <h3 id="gdmTitle" class="text-base font-bold text-slate-100">Detail Grading</h3>
                <p id="gdmSubtitle" class="text-xs text-slate-400"></p>
            </div>
            <button onclick="gradingCloseDetail()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white">&times;</button>
        </div>

        {{-- Info Row --}}
        <div id="gdmInfo" class="grid grid-cols-2 gap-3 border-b border-slate-700 p-5 sm:grid-cols-4"></div>

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-slate-700 px-5 pt-2">
            <button onclick="gradingDetailTab('detail')" id="gdmTabDetail"
                class="rounded-t-lg px-4 py-2 text-sm font-medium text-blue-400 border-b-2 border-blue-400">Detail Penilaian</button>
            <button onclick="gradingDetailTab('analisa')" id="gdmTabAnalisa"
                class="rounded-t-lg px-4 py-2 text-sm font-medium text-slate-400 hover:text-slate-200 border-b-2 border-transparent">Analisa</button>
            <button onclick="gradingDetailTab('pica')" id="gdmTabPica"
                class="rounded-t-lg px-4 py-2 text-sm font-medium text-slate-400 hover:text-slate-200 border-b-2 border-transparent">PICA</button>
        </div>

        {{-- Tab Content --}}
        <div class="flex-1 overflow-y-auto p-5">
            {{-- Detail Penilaian --}}
            <div id="gdmPanelDetail">
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Pemeriksaan</th>
                            <th class="py-2 text-left">Hasil</th>
                            <th class="py-2 text-right">Nilai</th>
                        </tr>
                    </thead>
                    <tbody id="gdmDetailBody" class="divide-y divide-slate-800 text-slate-300"></tbody>
                </table>
                </div>
            </div>

            {{-- Analisa --}}
            <div id="gdmPanelAnalisa" class="hidden space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <div class="mb-3 text-sm font-semibold text-slate-300">Distribusi Nilai</div>
                        <div id="gdmAnalisaDistribusi" class="space-y-2 text-sm"></div>
                    </div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <div class="mb-3 text-sm font-semibold text-slate-300">Temuan Utama</div>
                        <div id="gdmAnalisaTemuan" class="space-y-2 text-sm text-slate-400"></div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                    <div class="mb-3 text-sm font-semibold text-slate-300">Item Nilai Rendah (perlu PICA)</div>
                    <div id="gdmAnalisaRendah" class="space-y-2 text-sm text-red-300"></div>
                </div>
            </div>

            {{-- PICA dari grading ini --}}
            <div id="gdmPanelPica" class="hidden">
                <p class="text-sm text-slate-400">PICA terkait grading ini:</p>
                <div id="gdmPicaList" class="mt-3 space-y-2 text-sm text-slate-300"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between border-t border-slate-700 px-5 py-3">
            <span id="gdmFooterInfo" class="text-xs text-slate-500"></span>
            <button onclick="gradingCloseDetail()" class="rounded-xl bg-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-600">Tutup</button>
        </div>
    </div>
</div>
@endsection
