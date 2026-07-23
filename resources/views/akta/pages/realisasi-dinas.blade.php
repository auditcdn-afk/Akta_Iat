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
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Sudah Dikunci</p>
            <p id="rdStatSelesai" class="mt-1 text-xl font-bold text-emerald-300">0</p>
        </div>
        <div class="rounded-xl border border-slate-800 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit Terkait</p>
            <p id="rdStatPlan" class="mt-1 text-xl font-bold text-violet-300">0</p>
        </div>
    </div>

    {{-- Pilih plan (kalau belum dikunci lewat URL) --}}
    <div id="rdPlanPickerCard" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-3">
        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-200">
            <span>🧾</span> Mulai Realisasi Dinas Baru
        </h3>
        <p class="text-xs text-slate-500">Pilih salah satu plan audit yang statusnya sudah "done" dan belum punya realisasi dinas. Setelah dipilih, plan tidak bisa diganti lagi (dikunci per plan).</p>
        <div class="flex flex-wrap gap-2">
            <select id="rdPlanSelect" class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Pilih plan audit...</option>
            </select>
            <button type="button" id="rdPlanStartBtn" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Mulai</button>
        </div>
    </div>

    {{-- Detail realisasi dinas untuk plan yang dikunci --}}
    <div id="rdDetailCard" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800 pb-4">
            <div>
                <a href="/akta/realisasi-dinas" class="text-xs text-slate-500 hover:text-slate-300">← Pilih Plan Lain</a>
                <h3 id="rdDetailPlanLabel" class="mt-1 text-base font-bold text-slate-100">-</h3>
                <p id="rdDetailNoSpt" class="text-xs text-slate-500">-</p>
            </div>
            <div class="flex items-center gap-2">
                <span id="rdStatusBadge" class="inline-flex rounded-full border px-3 py-1 text-xs font-bold"></span>
                <button type="button" id="rdSelesaiBtn" class="hidden rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition">✅ Selesai &amp; Kunci</button>
                <button type="button" id="rdBukaKunciBtn" class="hidden rounded-xl border border-amber-500/40 px-4 py-2 text-sm font-semibold text-amber-300 hover:bg-amber-500/10 transition">🔓 Buka Kunci (Admin)</button>
            </div>
        </div>

        <div id="rdLockedNotice" class="hidden rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"></div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Personil <span class="normal-case font-normal text-slate-500">(otomatis dari tim plan, bisa disesuaikan)</span></label>
                <div id="rdPersonilEditWrap" class="flex gap-2">
                    <input id="rdPersonilInput" type="text" list="rdPersonilOptions" placeholder="Cari / ketik nama personil..."
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <datalist id="rdPersonilOptions"></datalist>
                    <button type="button" id="rdPersonilAddBtn"
                        class="shrink-0 rounded-xl border border-blue-500/40 px-4 py-2 text-sm font-semibold text-blue-300 hover:bg-blue-500/10 transition">+ Tambah</button>
                </div>
                <div id="rdPersonilChips" class="mt-2 flex flex-wrap gap-2"></div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Bukti <span class="normal-case font-normal text-slate-500">(satu file untuk seluruh plan ini)</span></label>
                <div id="rdBuktiUploadWrap" class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">
                    <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                        Pilih File
                        <input id="rdFileInput" type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                    </label>
                    <span id="rdFileName" class="truncate text-sm text-slate-400">Belum ada file</span>
                </div>
                <div id="rdBuktiExisting" class="mt-2 hidden text-sm">
                    <a id="rdBuktiExistingLink" href="#" target="_blank" rel="noopener" class="text-blue-400 hover:underline">📎 Lihat bukti tersimpan</a>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-800 pt-4">
            <h4 class="mb-3 text-sm font-bold text-slate-200">Rincian Jenis Pengeluaran</h4>

            <div id="rdItemFormWrap" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 rounded-xl border border-slate-800 bg-slate-950 p-4">
                <select id="rdItemJenis" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></select>
                <input id="rdItemCatatan" type="text" placeholder="Catatan (opsional)"
                    class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <div class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-900 px-2 py-1">
                    <button type="button" id="rdItemNominalMinus" class="h-8 w-8 shrink-0 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700">−</button>
                    <input id="rdItemNominal" type="number" min="0" step="1000" value="0"
                        class="w-full bg-transparent px-2 py-1.5 text-right text-sm text-slate-100 outline-none">
                    <button type="button" id="rdItemNominalPlus" class="h-8 w-8 shrink-0 rounded-lg bg-slate-800 text-slate-200 hover:bg-slate-700">+</button>
                </div>
                <button type="button" id="rdItemAddBtn" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">+ Tambah Item</button>
            </div>

            <div class="mt-4 overflow-hidden rounded-xl border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Pengeluaran</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rdItemsTableBody" class="divide-y divide-slate-800 text-slate-200">
                        <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Belum ada item pengeluaran.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-950/60">
                            <td colspan="2" class="px-3 py-2 text-right text-sm font-bold text-slate-300">Total</td>
                            <td id="rdItemsTotal" class="px-3 py-2 text-right text-sm font-bold text-blue-300">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Filter & tabel semua realisasi dinas (analisa) --}}
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

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Dibuat</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Personil</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah Item</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Total Nominal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
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
