@extends('akta.layouts.app')

@section('title', 'BU Performance - AKTA IAT')
@section('page_title', 'BU Performance')
@section('page_description', 'Penilaian personil kinerja jelek per unit usaha')

@push('scripts')
    @vite('resources/js/akta-bu-performance.js')
@endpush

@section('content')
<section class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">BU Performance</h2>
            <p class="mt-1 text-sm text-slate-400">
                Penilaian Personil yang Kinerja Jelek (Sikap, Perilaku, Karakter, Kualitas) per unit usaha.
            </p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <select id="bupUnitFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Unit Usaha</option>
            </select>
            <select id="bupBulanFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Bulan</option>
            </select>
            <input id="bupSearch" type="search" placeholder="Cari unit usaha / auditor..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 sm:w-60">
            <button id="bupExportFilterBtn" type="button"
                class="rounded-xl border border-emerald-500/40 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/10 transition">
                Export Tampilan
            </button>
            <button id="bupExportAllBtn" type="button"
                class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                Export Semua
            </button>
        </div>
    </div>

    {{-- Alert --}}
    <div id="bupAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Tabel rekap (output seperti Excel) --}}
    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-r border-slate-700">Unit Usaha</th>
                        <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-r border-slate-700">Auditor</th>
                        <th colspan="3" class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-300 border-b border-slate-700">
                            Penilaian Personil yang Kinerja Jelek (Sikap, Perilaku, Karakter, Kualitas)
                        </th>
                        <th rowspan="2" class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400"></th>
                    </tr>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-r border-slate-700">PIC</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-r border-slate-700">Jabatan</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-r border-slate-700">Uraian</th>
                    </tr>
                </thead>
                <tbody id="bupTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</section>
@endsection
