@extends('akta.layouts.app')

@section('title', 'Audit - AKTA IAT')
@section('page_title', 'Audit')
@section('page_description', 'Plan audit yang siap dikerjakan — klik Mulai Audit untuk memulai')

@section('content')
<section class="space-y-5">
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Plan Audit</h2>
            <p class="mt-1 text-sm text-slate-400">
                Plan yang telah disetujui COO akan muncul di sini. Klik <strong class="text-slate-200">Mulai Audit</strong> untuk memulai pelaksanaan.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="auditSearch" type="search" placeholder="Cari no SPT / cabang..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-64">

            <select id="auditStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua</option>
                <option value="scheduled">Terjadwal</option>
                <option value="running">Sedang Berjalan</option>
                <option value="cabang_active">Cabang Aktif</option>
            </select>
        </div>
    </div>

    <div id="auditAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No SPT / Cabang</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Audit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tgl Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tim</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat data audit...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Section Pemeriksaan (muncul setelah plan dipilih) ── --}}
    <div id="pemeriksaanSection" class="hidden space-y-4">
        <div class="flex items-center justify-between rounded-2xl border border-slate-700 bg-slate-900 px-5 py-3">
            <div>
                <h3 class="font-bold text-slate-100">Pemeriksaan: <span id="pemeriksaanPlanLabel" class="text-blue-300">-</span></h3>
                <p class="text-xs text-slate-500 mt-0.5">Pilih jenis pemeriksaan di bawah ini</p>
            </div>
            <button type="button" id="closePemeriksaanBtn"
                class="text-xs text-slate-400 hover:text-slate-200 border border-slate-700 rounded-lg px-3 py-1.5">
                Tutup
            </button>
        </div>

        {{-- Tab bar --}}
        <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-800 bg-slate-900 p-2">
            <button type="button" data-tab="kas"
                class="audit-tab-btn rounded-xl px-4 py-2 text-sm font-semibold transition bg-blue-600 text-white">
                Pemeriksaan Kas
            </button>
            <button type="button" data-tab="smh"
                class="audit-tab-btn rounded-xl px-4 py-2 text-sm font-semibold transition text-slate-300 hover:bg-slate-800">
                Pemeriksaan SMH
            </button>
            <button type="button" data-tab="perlengkapan"
                class="audit-tab-btn rounded-xl px-4 py-2 text-sm font-semibold transition text-slate-300 hover:bg-slate-800">
                Perlengkapan di luar SMH
            </button>
            <button type="button" data-tab="bank"
                class="audit-tab-btn rounded-xl px-4 py-2 text-sm font-semibold transition text-slate-300 hover:bg-slate-800">
                Pemeriksaan Bank
            </button>
        </div>

        {{-- Panel: Pemeriksaan Kas --}}
        <div id="tabPanel-kas" class="audit-tab-panel space-y-4">
            <div class="flex items-center justify-between">
                <div class="grid gap-4 md:grid-cols-5 flex-1">
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
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex gap-3">
                    <input id="kasSearch" type="search" placeholder="Cari nama pos..."
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 w-64">
                    <select id="kasSelisihFilter"
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">Semua</option>
                        <option value="true">Ada Selisih</option>
                    </select>
                </div>
                <button id="openCreateKasButton" type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                    + Tambah Pemeriksaan Kas
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800">
                        <thead class="bg-slate-950/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Pos Kas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Plan Audit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Saldo Fisik</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Saldo Buku</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Selisih</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Keterangan</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="kasTableBody" class="divide-y divide-slate-800">
                            <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">Pilih plan audit untuk melihat pemeriksaan kas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel: SMH --}}
        <div id="tabPanel-smh" class="audit-tab-panel hidden">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-10 text-center">
                <h3 class="text-lg font-bold text-slate-200">Pemeriksaan SMH</h3>
                <p class="mt-2 text-sm text-slate-400">Modul pemeriksaan Stok Material Honda (SMH) akan segera tersedia.</p>
            </div>
        </div>

        {{-- Panel: Perlengkapan --}}
        <div id="tabPanel-perlengkapan" class="audit-tab-panel hidden">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-10 text-center">
                <h3 class="text-lg font-bold text-slate-200">Perlengkapan di luar SMH</h3>
                <p class="mt-2 text-sm text-slate-400">Modul pemeriksaan perlengkapan di luar SMH akan segera tersedia.</p>
            </div>
        </div>

        {{-- Panel: Bank --}}
        <div id="tabPanel-bank" class="audit-tab-panel hidden">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-10 text-center">
                <h3 class="text-lg font-bold text-slate-200">Pemeriksaan Bank</h3>
                <p class="mt-2 text-sm text-slate-400">Modul pemeriksaan bank akan segera tersedia.</p>
            </div>
        </div>
    </div>
</section>

{{-- Modal Tambah/Edit Pemeriksaan Kas --}}
<div id="kasModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="kasModalTitle" class="text-lg font-bold">Tambah Pemeriksaan Kas</h3>
                <p class="text-sm text-slate-400">Data wajib terhubung ke Plan Audit.</p>
            </div>
            <button id="closeKasModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
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
                    <p class="mt-2 text-xs text-slate-500">Format harus JSON valid.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelKasFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button type="submit" id="saveKasButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Detail Pemeriksaan Kas --}}
<div id="kasDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="kasDetailTitle" class="text-lg font-bold">Detail Pemeriksaan Kas</h3>
                <p id="kasDetailSubtitle" class="text-sm text-slate-400">-</p>
            </div>
            <button id="closeKasDetailButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
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
                <pre id="detailJsonPreview" class="mt-3 overflow-x-auto rounded-xl border border-slate-800 bg-slate-950 p-4 text-xs text-slate-300"></pre>
            </div>
        </div>
    </div>
</div>

{{-- Modal Detail Plan --}}
<div id="auditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Detail Plan Audit</h3>
                <p class="text-sm text-slate-400">Tinjau data plan dan mulai pelaksanaan audit.</p>
            </div>
            <button id="closeAuditModal" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>
        <div class="space-y-5 px-5 py-5">
            <input type="hidden" id="auditPlanId">
            <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Data Plan Audit</h4>
                <dl id="auditPlanDetail" class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm"></dl>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Riwayat Status Birokrasi</h4>
                <ol id="auditTimeline" class="space-y-3 text-sm"></ol>
            </div>
            <div id="auditActions" class="flex justify-end gap-3 border-t border-slate-800 pt-4"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-audit.js')
@endpush
