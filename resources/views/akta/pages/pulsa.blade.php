@extends('akta.layouts.app')

@section('title', 'Pulsa - SIMPAS-IAT')
@section('page_title', 'Realisasi Pulsa')
@section('page_description', 'Input & realisasi biaya pulsa auditor per bulan')

@push('scripts')
    @vite('resources/js/akta-pulsa.js')
@endpush

@section('content')
<section class="space-y-5">

    {{-- Header: status periode + filter tahun/bulan + export --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <span id="pulsaPeriodeBadge" class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300">
                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                <span id="pulsaPeriodeText">Memuat periode...</span>
            </span>
            <button type="button" id="pulsaTogglePeriodeBtn" class="hidden rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                Tutup Periode
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select id="pulsaTahunFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></select>
            <select id="pulsaBulanFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></select>
            <button id="pulsaCetakBonBtn" type="button"
                class="rounded-xl border border-blue-500/40 px-4 py-2 text-sm font-semibold text-blue-300 hover:bg-blue-500/10 transition">
                🖨️ Cetak Bon
            </button>
            <button id="pulsaExportBtn" type="button"
                class="rounded-xl border border-emerald-500/40 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/10 transition">
                📊 Export Excel
            </button>
        </div>
    </div>

    <div id="pulsaAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Form Input --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-200">
            <span>📝</span> Form Input Realisasi Pulsa
        </h3>

        <form id="pulsaForm" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama <span class="text-red-400">*</span></label>
                    <select id="pulsaNama" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">— Pilih Nama —</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jabatan</label>
                    <input id="pulsaJabatan" type="text" readonly
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-400 outline-none cursor-not-allowed">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal <span class="text-red-400">*</span></label>
                    <input id="pulsaTanggal" type="date" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nomor HP <span class="text-red-400">*</span></label>
                    <input id="pulsaNomorHp" type="text" required placeholder="08xx-xxxx-xxxx"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Operator</label>
                    <select id="pulsaOperator"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">— Pilih —</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal (Rp) <span class="text-red-400">*</span></label>
                    <input id="pulsaNominal" type="text" inputmode="numeric" required value="0"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Upload Bon Pulsa <span class="text-red-400">*</span> <span class="normal-case font-normal text-slate-500">(gambar/PDF, wajib)</span></label>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">
                        <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                            Pilih File
                            <input id="pulsaFileInput" type="file" accept=".jpg,.jpeg,.png,.pdf" required class="hidden">
                        </label>
                        <span id="pulsaFileName" class="truncate text-sm text-slate-400">Belum ada file</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-800 pt-4">
                <button type="submit" id="pulsaSaveBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    💾 Simpan Realisasi
                </button>
            </div>
        </form>
    </div>

    {{-- Tabel data --}}
    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jabatan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Operator</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No HP</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Bon</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pulsaTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr><td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between border-t border-slate-800 bg-slate-950/60 px-4 py-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</span>
            <div class="flex items-center gap-4 text-sm">
                <span id="pulsaTotalCount" class="text-slate-400">0 record</span>
                <span id="pulsaTotalNominal" class="font-bold text-emerald-400">Rp 0</span>
            </div>
        </div>
    </div>
</section>
@endsection
