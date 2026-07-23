@extends('akta.layouts.app')

@section('title', 'Realisasi Dinas - SIMPAS-IAT')
@section('page_title', 'Realisasi Dinas')
@section('page_description', 'Settlement biaya perjalanan dinas per plan audit yang sudah selesai (done)')

@push('scripts')
    @vite('resources/js/akta-realisasi-dinas.js')
@endpush

@section('content')
<section class="space-y-5">

    <div id="rdAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-blue-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Realisasi</p>
            <p id="rdStatTotal" class="mt-1 text-xl font-bold text-blue-300">Rp 0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-emerald-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah Entri</p>
            <p id="rdStatCount" class="mt-1 text-xl font-bold text-emerald-300">0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit Terkait</p>
            <p id="rdStatPlan" class="mt-1 text-xl font-bold text-violet-300">0</p>
        </div>
    </div>

    {{-- Form Realisasi Dinas --}}
    <div id="rdFormCard" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-200">
            <span>🧾</span> Form Realisasi Dinas
        </h3>

        <form id="rdForm" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit (Done) <span class="text-red-400">*</span></label>
                    <select id="rdPlanSelect" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">Pilih plan audit...</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Settlement <span class="text-red-400">*</span></label>
                    <input id="rdTanggal" type="date" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Pengeluaran <span class="text-red-400">*</span></label>
                    <select id="rdJenisPengeluaran" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </select>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Personil <span class="text-red-400">*</span> <span class="normal-case font-normal text-slate-500">(bisa lebih dari satu)</span></label>
                    <div class="flex gap-2">
                        <input id="rdPersonilInput" type="text" list="rdPersonilOptions" placeholder="Cari / ketik nama personil..."
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <datalist id="rdPersonilOptions"></datalist>
                        <button type="button" id="rdPersonilAddBtn"
                            class="shrink-0 rounded-xl border border-blue-500/40 px-4 py-2 text-sm font-semibold text-blue-300 hover:bg-blue-500/10 transition">+ Tambah</button>
                    </div>
                    <div id="rdPersonilChips" class="mt-2 flex flex-wrap gap-2"></div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal <span class="text-red-400">*</span></label>
                    <div class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-2 py-1">
                        <button type="button" id="rdNominalMinus" class="h-8 w-8 shrink-0 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700">−</button>
                        <input id="rdNominal" type="number" min="0" step="1000" value="0" required
                            class="w-full bg-transparent px-2 py-1.5 text-right text-sm text-slate-100 outline-none">
                        <button type="button" id="rdNominalPlus" class="h-8 w-8 shrink-0 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700">+</button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan</label>
                    <textarea id="rdCatatan" rows="3" placeholder="Catatan tambahan (opsional)..."
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Bukti <span class="normal-case font-normal text-slate-500">(foto/PDF, opsional)</span></label>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">
                        <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                            Pilih File
                            <input id="rdFileInput" type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                        </label>
                        <span id="rdFileName" class="truncate text-sm text-slate-400">Belum ada file</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-800 pt-4">
                <button type="submit" id="rdSaveBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    💾 Simpan Realisasi Dinas
                </button>
            </div>
        </form>
    </div>

    {{-- Filter --}}
    <div class="flex flex-wrap items-center gap-2">
        <select id="rdFilterJenis" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Jenis Pengeluaran</option>
        </select>
        <input id="rdFilterTahun" type="number" placeholder="Tahun" min="2000" max="2100"
            class="w-28 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
        <select id="rdFilterBulan" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Bulan</option>
        </select>
        <button type="button" id="rdFilterApplyBtn" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">Terapkan Filter</button>
        <button type="button" id="rdFilterResetBtn" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">Reset</button>
    </div>

    {{-- Tabel data --}}
    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Personil</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Pengeluaran</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Bukti</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="rdTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
