@extends('akta.layouts.app')

@section('title', 'Audit Detail Kas - AKTA IAT')
@section('page_title', 'Audit Detail Kas')
@section('page_description', 'Pemeriksaan kas berdasarkan Plan Audit')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Pemeriksaan Kas</h2>
            <p class="mt-1 text-sm text-slate-400">
                Kelola saldo fisik, saldo buku, selisih, dan detail pemeriksaan kas per Plan Audit.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="kasSearch" type="search" placeholder="Cari no SPT / cabang / nama pos..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-80">

            <select id="kasSelisihFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua</option>
                <option value="true">Ada Selisih</option>
            </select>

            <button id="openCreateKasButton" type="button"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Tambah
            </button>
        </div>
    </div>

    <div id="kasAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-4 md:grid-cols-5">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Pos</div>
            <div id="kasTotalPosStat" class="mt-2 text-2xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Fisik</div>
            <div id="kasSaldoFisikStat" class="mt-2 text-lg font-bold text-blue-300">Rp0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Buku</div>
            <div id="kasSaldoBukuStat" class="mt-2 text-lg font-bold text-amber-300">Rp0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Selisih</div>
            <div id="kasTotalSelisihStat" class="mt-2 text-lg font-bold text-red-300">Rp0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pos Selisih</div>
            <div id="kasPosSelisihStat" class="mt-2 text-2xl font-bold text-red-300">0</div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
        <label class="mb-2 block text-sm font-medium text-slate-300">Filter Plan Audit</label>
        <select id="kasPlanFilter"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Plan Audit</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Pos Kas
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Plan Audit
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Saldo Fisik
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Saldo Buku
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Selisih
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Keterangan
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody id="kasTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat pemeriksaan kas...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="kasModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="kasModalTitle" class="text-lg font-bold">Tambah Pemeriksaan Kas</h3>
                <p class="text-sm text-slate-400">Data wajib terhubung ke Plan Audit.</p>
            </div>

            <button id="closeKasModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="kasForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="kasId">

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Plan Audit</label>
                    <select id="planAuditId" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">Pilih Plan Audit</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Nama Pos Kas</label>
                    <input id="namaPos" type="text" required placeholder="Contoh: Kas Operasional Dealer"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Saldo Fisik</label>
                    <input id="saldoFisik" type="number" step="0.01" min="0"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Saldo Buku</label>
                    <input id="saldoBuku" type="number" step="0.01" min="0"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Keterangan</label>
                    <textarea id="keterangan" rows="3"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Detail JSON</label>
                    <textarea id="detailJson" rows="8"
                        class="font-mono w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-100 outline-none focus:border-blue-500"
                        placeholder='{"penerimaan":[],"pengeluaran":[],"blanko":[]}'></textarea>
                    <p class="mt-2 text-xs text-slate-500">
                        Field ini disimpan ke kolom detail_json. Format harus JSON valid.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelKasFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    Batal
                </button>

                <button type="submit" id="saveKasButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div id="kasDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="kasDetailTitle" class="text-lg font-bold">Detail Pemeriksaan Kas</h3>
                <p id="kasDetailSubtitle" class="text-sm text-slate-400">-</p>
            </div>

            <button id="closeKasDetailButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <div class="space-y-5 px-5 py-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Fisik</div>
                    <div id="detailSaldoFisik" class="mt-2 text-xl font-bold text-blue-300">Rp0</div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Buku</div>
                    <div id="detailSaldoBuku" class="mt-2 text-xl font-bold text-amber-300">Rp0</div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selisih</div>
                    <div id="detailSelisih" class="mt-2 text-xl font-bold text-red-300">Rp0</div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <h4 class="font-bold text-slate-100">Informasi</h4>
                <div id="detailInfo" class="mt-3 grid gap-3 text-sm text-slate-300 md:grid-cols-3"></div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <h4 class="font-bold text-slate-100">Detail JSON</h4>
                <pre id="detailJsonPreview"
                    class="mt-3 overflow-x-auto rounded-xl border border-slate-800 bg-slate-950 p-4 text-xs text-slate-300"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-audit-detail-kas.js')
@endpush
