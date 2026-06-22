@extends('akta.layouts.app')

@section('title', 'Database - AKTA IAT')
@section('page_title', 'Database')
@section('page_description', 'Master data SMH &mdash; Harga, Plafon, Perlengkapan, Onhand, dan Unit Usaha')

@section('content')
<section class="space-y-5">

    {{-- Tab Navigation --}}
    <div class="overflow-x-auto">
        <div class="flex min-w-max gap-1 rounded-2xl border border-slate-800 bg-slate-900 p-2">
            @foreach([
                ['harga-smh',    'Harga SMH',         'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['plafon',       'Plafon',             'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['perlengkapan', 'Perlengkapan',       'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['unit-usaha',   'Unit Usaha',         'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                ['grading',      'Database Grading',   'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['mt',           'Database MT',        'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
                ['het',          'Database HET',       'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ] as [$key, $label, $icon])
            <button
                type="button"
                data-tab="{{ $key }}"
                class="db-tab-btn flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition-all"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Alert --}}
    <div id="dbAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Tab Panels --}}
    @foreach([
        ['harga-smh', 'HARGA SMH', 'Kode Model', 'Nama SMH', 'Harga (Rp)', 'Kode Model | Nama SMH | Harga (Rp)'],
        ['plafon', 'PLAFON', 'Kode', 'Nama', 'Nilai', 'Kode | Nama | Nilai | Keterangan'],
        ['perlengkapan', 'PERLENGKAPAN', 'Kode', 'Nama', 'Satuan', 'Kode | Nama | Satuan | Qty | Keterangan'],
        ['unit-usaha', 'UNIT USAHA', 'Kode', 'Nama', 'Alamat', 'Kode | Nama | Alamat | Keterangan'],
        ['grading', 'GRADING', 'Kode', 'Nama', 'Grade', 'Kode | Nama | Grade | Nilai Min | Nilai Max | Keterangan'],
        ['mt', 'MANAGEMENT TRAINEE (MT)', 'Kode', 'Nama', 'Jenis', 'Kode | Nama | Jenis | Periode | Keterangan'],
        ['het', 'HET', 'Kode', 'Nama', 'Harga HET', 'Kode | Nama | Harga HET | Satuan | Keterangan'],
    ] as [$key, $title, $col1, $col2, $col3, $importFmt])
    <div id="tab-{{ $key }}" class="db-panel hidden space-y-4">

        {{-- Panel Header --}}
        <div class="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-950 px-5 py-3">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582 4-8 4s8 1.79 8 4" />
                </svg>
                <span class="text-xs font-bold uppercase tracking-widest text-slate-200">DATABASE {{ $title }}</span>
            </div>
            <span class="text-xs text-slate-500" id="admin-label-{{ $key }}"></span>
        </div>

        {{-- Import Zone (admin only) --}}
        <div id="import-zone-{{ $key }}" class="hidden">
            <div
                id="dropzone-{{ $key }}"
                class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-slate-700 bg-slate-900/60 py-10 transition hover:border-blue-500 hover:bg-slate-900"
                data-type="{{ $key }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <p class="text-sm text-slate-400">
                    Drag &amp; drop file Excel atau
                    <label for="fileInput-{{ $key }}" class="cursor-pointer font-semibold text-blue-400 hover:underline">klik untuk pilih file</label>
                </p>
                <p class="font-mono text-xs text-slate-600">Format kolom:&nbsp; {{ $importFmt }}</p>
                <a href="/templates/template-{{ $key }}.xlsx" download class="text-xs text-blue-500 hover:underline">↓ Download template Excel</a>
                <input type="file" id="fileInput-{{ $key }}" class="hidden" accept=".xlsx,.csv,.txt" data-type="{{ $key }}">
            </div>
        </div>

        {{-- Table Header: count + actions --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <input
                    id="search-{{ $key }}"
                    type="search"
                    placeholder="Cari data..."
                    data-type="{{ $key }}"
                    class="w-56 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"
                >
                <span id="count-{{ $key }}" class="text-sm text-slate-500">— data</span>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    id="hapus-btn-{{ $key }}"
                    data-type="{{ $key }}"
                    class="hapus-btn hidden rounded-xl border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10"
                >
                    Hapus Data
                </button>
                <button
                    type="button"
                    id="import-btn-{{ $key }}"
                    data-type="{{ $key }}"
                    class="import-btn hidden rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500"
                >
                    Import Excel ↑
                </button>
                <button
                    type="button"
                    id="tambah-btn-{{ $key }}"
                    data-type="{{ $key }}"
                    class="tambah-btn hidden rounded-xl border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                >
                    + Tambah
                </button>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400 w-12">No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $col1 }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $col2 }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $col3 }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400 admin-col hidden">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-{{ $key }}" class="divide-y divide-slate-800">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Memuat data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div id="pagination-{{ $key }}" class="hidden flex items-center justify-between border-t border-slate-800 px-4 py-3">
                <span id="pag-info-{{ $key }}" class="text-xs text-slate-500"></span>
                <div class="flex gap-2">
                    <button type="button" id="pag-prev-{{ $key }}" data-type="{{ $key }}" class="pag-prev rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-800 disabled:opacity-40">← Prev</button>
                    <button type="button" id="pag-next-{{ $key }}" data-type="{{ $key }}" class="pag-next rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-800 disabled:opacity-40">Next →</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach

</section>

{{-- ── Form Modal ──────────────────────────────────────────── --}}
<div id="dbModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="dbModalTitle" class="text-lg font-bold">Tambah Data</h3>
                <p id="dbModalSub" class="text-sm text-slate-400"></p>
            </div>
            <button id="closeDbModal" type="button" class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>
        <form id="dbForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="dbFormId">
            <input type="hidden" id="dbFormType">
            <div id="dbFormFields" class="grid gap-4 sm:grid-cols-2"></div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelDbModal" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Upload Progress Overlay --}}
<div id="uploadOverlay" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/70">
    <div class="w-80 rounded-2xl border border-slate-800 bg-slate-900 p-6 text-center shadow-2xl">
        <div class="mx-auto mb-4 h-10 w-10 animate-spin rounded-full border-4 border-slate-700 border-t-blue-500"></div>
        <p class="text-sm font-semibold text-slate-200">Mengimport data...</p>
        <p class="mt-1 text-xs text-slate-500">Harap tunggu, jangan tutup halaman ini.</p>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-database.js')
@endpush
