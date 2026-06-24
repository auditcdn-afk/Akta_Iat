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
        <div id="tabPanel-kas" class="audit-tab-panel space-y-5">
            <input type="hidden" id="kasId">
            <input type="hidden" id="kasPlanAuditId">

            {{-- ── KAS BESAR ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">💰 Kas Besar</div>
                <div class="space-y-4 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Tanggal H-1 Pemeriksaan)</label>
                            <input id="kbSaldoAwalTgl" type="date"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Rp)</label>
                            <input id="kbSaldoAwal" type="text" inputmode="numeric" value=""
                                class="kb-calc w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>

                    {{-- Penerimaan --}}
                    <div>
                        <div class="mb-2 text-sm font-bold text-emerald-600">▲ Penerimaan</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kbPenerimaanBody"></tbody>
                        </table>
                        <button type="button" data-add="kbPenerimaan" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">+ Tambah Penerimaan</button>
                    </div>

                    {{-- Pengeluaran --}}
                    <div>
                        <div class="mb-2 text-sm font-bold text-red-500">▼ Pengeluaran</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kbPengeluaranBody"></tbody>
                        </table>
                        <button type="button" data-add="kbPengeluaran" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">+ Tambah Pengeluaran</button>
                    </div>

                    {{-- Ringkasan Kas Besar --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between py-1 text-slate-600"><span>Saldo Awal</span><span id="kbSumSaldoAwal" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-600"><span>Total Penerimaan</span><span id="kbSumPenerimaan" class="font-semibold text-emerald-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-600"><span>Total Pengeluaran</span><span id="kbSumPengeluaran" class="font-semibold text-red-500">Rp 0</span></div>
                        <div class="mt-1 flex justify-between border-t border-slate-300 py-2 font-bold text-slate-800"><span>Saldo Buku (Sistem)</span><span id="kbSaldoBuku">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-600"><span>Saldo Fisik (Uang Fisik)</span><span id="kbSaldoFisik" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 font-bold"><span class="text-red-500">Selisih</span><span id="kbSelisih" class="text-red-500">Rp 0</span></div>
                        <div class="mt-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan</label>
                            <input id="kbKeterangan" type="text" placeholder="contoh: Selisih lebih pembulatan"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── KAS KECIL ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#2d8a4e] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🪙 Kas Kecil</div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cadangan Kas Kecil (Rp)</label>
                        <input id="kkCadangan" type="text" inputmode="numeric" value=""
                            class="kk-calc w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-bold text-amber-600">🧾 Rincian Bon Gantung Kas Kecil</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kkBonBody"></tbody>
                        </table>
                        <button type="button" data-add="kkBon" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">+ Tambah Bon Gantung</button>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between py-1 text-slate-600"><span>Cadangan Kas Kecil</span><span id="kkSumCadangan" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-600"><span>Total Bon Gantung</span><span id="kkSumBon" class="font-semibold text-amber-600">Rp 0</span></div>
                        <div class="mt-1 flex justify-between border-t border-slate-300 py-2 font-bold text-slate-800"><span>Saldo Buku (Sistem)</span><span id="kkSaldoBuku">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-600"><span>Saldo Fisik (Uang Fisik)</span><span id="kkSaldoFisik" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 font-bold"><span class="text-red-500">Selisih</span><span id="kkSelisih" class="text-red-500">Rp 0</span></div>
                        <div class="mt-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan</label>
                            <input id="kkKeterangan" type="text" placeholder="contoh: Selisih lebih pembulatan"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── RINCIAN PECAHAN NOMINAL UANG KAS ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">💵 Rincian Pecahan Nominal Uang Kas</div>
                <div class="overflow-x-auto p-5">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-right">Pecahan (Rp)</th>
                                <th class="px-3 py-2 text-center">Jumlah Lembar/Keping<br>Kas Besar</th>
                                <th class="px-3 py-2 text-right">Total<br>Kas Besar</th>
                                <th class="px-3 py-2 text-center">Jumlah Lembar/Keping<br>Kas Kecil</th>
                                <th class="px-3 py-2 text-right">Total<br>Kas Kecil</th>
                            </tr>
                        </thead>
                        <tbody id="pecahanBody"></tbody>
                        <tfoot class="bg-slate-100 font-bold">
                            <tr>
                                <td class="px-3 py-2 text-right">TOTAL</td>
                                <td class="px-3 py-2 text-center text-amber-600" id="pecahanTotalLembarBesar">0</td>
                                <td class="px-3 py-2 text-right" id="pecahanTotalBesar">Rp 0</td>
                                <td class="px-3 py-2 text-center text-amber-600" id="pecahanTotalLembarKecil">0</td>
                                <td class="px-3 py-2 text-right" id="pecahanTotalKecil">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- ── REGISTER BLANKO ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">📋 Register Blanko yang Belum Digunakan</div>
                <div class="space-y-5 p-5">
                    <div>
                        <div class="mb-2 border-b border-slate-200 pb-1 text-sm font-bold text-slate-700">H1</div>
                        <table class="w-full text-sm">
                            <tbody id="blankoH1Body"></tbody>
                        </table>
                        <button type="button" data-add="blankoH1" class="add-row-btn mt-2 rounded-lg border border-dashed border-slate-400 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">+ Tambah Register Blanko H1</button>
                    </div>
                    <div>
                        <div class="mb-2 border-b border-slate-200 pb-1 text-sm font-bold text-slate-700">H2</div>
                        <table class="w-full text-sm">
                            <tbody id="blankoH2Body"></tbody>
                        </table>
                        <button type="button" data-add="blankoH2" class="add-row-btn mt-2 rounded-lg border border-dashed border-slate-400 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">+ Tambah Register Blanko H2</button>
                    </div>
                </div>
            </div>

            {{-- Aksi simpan --}}
            <div class="flex justify-end gap-3">
                <button type="button" id="saveKasFormBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan Pemeriksaan Kas
                </button>
            </div>
        </div>

        {{-- Panel: SMH --}}
        <div id="tabPanel-smh" class="audit-tab-panel hidden space-y-5">

            {{-- Upload Onhand --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="flex items-center justify-between bg-[#1e3a5f] px-5 py-3 text-white">
                    <span class="text-sm font-bold uppercase tracking-wide">📋 Upload File Onhand SMH</span>
                    <span id="smhTglOnhand" class="text-xs text-blue-200"></span>
                </div>
                <div class="flex flex-wrap items-end gap-4 p-5">
                    <div class="flex-1 min-w-60">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">File Onhand (.xls / .xlsx)</label>
                        <input id="smhFileInput" type="file" accept=".xls,.xlsx"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <button type="button" id="smhUploadBtn"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 whitespace-nowrap">
                        Upload &amp; Proses
                    </button>
                    <button type="button" id="smhSyncBtn"
                        class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 whitespace-nowrap hidden">
                        🔗 Sinkron Perlengkapan
                    </button>
                </div>
            </div>

            {{-- Summary --}}
            <div id="smhSummary" class="hidden grid gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="smhTotalUnit">0</div>
                    <div class="text-xs text-slate-400 mt-1">Total Unit</div>
                </div>
                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-400" id="smhTotalAda">0</div>
                    <div class="text-xs text-slate-400 mt-1">Ditemukan</div>
                </div>
                <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-red-400" id="smhTotalTidakAda">0</div>
                    <div class="text-xs text-slate-400 mt-1">Tidak Ditemukan</div>
                </div>
                <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-amber-400" id="smhTotalBelum">0</div>
                    <div class="text-xs text-slate-400 mt-1">Belum Diperiksa</div>
                </div>
            </div>

            {{-- Scan / Cari unit --}}
            <div id="smhScanBox" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#2d8a4e] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🔍 Pemeriksaan Fisik — Scan / Cari Unit</div>
                <div class="p-5 space-y-3">
                    <div class="relative flex gap-3">
                        <div class="relative flex-1">
                            <input id="smhScanInput" type="text" autocomplete="off"
                                placeholder="Scan atau ketik No. Mesin / No. Rangka..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500">
                            {{-- Suggestions list --}}
                            <ul id="smhSuggestions"
                                class="absolute left-0 right-0 top-full z-50 hidden max-h-60 overflow-y-auto rounded-b-lg border border-t-0 border-slate-300 bg-white shadow-lg">
                            </ul>
                        </div>
                        <button type="button" id="smhScanBtn"
                            class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 whitespace-nowrap">
                            Cek
                        </button>
                    </div>
                    <p class="text-xs text-slate-400">Ketik minimal 2 karakter untuk melihat saran unit, atau scan barcode langsung.</p>
                    <div id="smhScanResult" class="hidden rounded-xl border p-4 text-sm"></div>
                </div>
            </div>

            {{-- Tabel unit --}}
            <div id="smhTableWrap" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="flex items-center justify-between bg-[#1e3a5f] px-5 py-3 text-white">
                    <span class="text-sm font-bold uppercase tracking-wide">Daftar Unit On Hand</span>
                    <div class="flex gap-2 items-center text-xs">
                        <select id="smhFilterStatus"
                            class="rounded-lg border border-blue-300 bg-[#1e3a5f] px-2 py-1 text-white text-xs outline-none">
                            <option value="">Semua Status</option>
                            <option value="ada">Ditemukan</option>
                            <option value="tidak_ada">Tidak Ditemukan</option>
                            <option value="belum">Belum Diperiksa</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">No</th>
                                <th class="px-3 py-2 text-left">No. Mesin</th>
                                <th class="px-3 py-2 text-left">No. Rangka</th>
                                <th class="px-3 py-2 text-left">No. SPB</th>
                                <th class="px-3 py-2 text-left">Tgl SPB</th>
                                <th class="px-3 py-2 text-center">Umur</th>
                                <th class="px-3 py-2 text-left">Model</th>
                                <th class="px-3 py-2 text-left">Warna</th>
                                <th class="px-3 py-2 text-left">Gudang</th>
                                <th class="px-3 py-2 text-center w-40">Status Fisik</th>
                                <th class="px-3 py-2 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="smhTableBody"></tbody>
                    </table>
                </div>
            </div>

            {{-- Hasil sync perlengkapan --}}
            <div id="smhSyncResult" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🔗 Hasil Sinkronisasi Perlengkapan</div>
                <div id="smhSyncBody" class="p-5 space-y-2 text-sm"></div>
            </div>
        </div>

        {{-- Panel: Perlengkapan di luar SMH --}}
        <div id="tabPanel-perlengkapan" class="audit-tab-panel hidden space-y-5">

            {{-- Form Tambah --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-300">📦 Perlengkapan di Luar SMH</h3>
                    <span id="plSmhBadge" class="hidden rounded-full bg-blue-900/50 px-3 py-0.5 text-xs text-blue-300"></span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">No Plan</label>
                        <input id="plNoPlan" type="text" readonly
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Unit Usaha</label>
                        <input id="plNamaUnit" type="text" readonly
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Pemeriksa <span class="text-red-400">*</span></label>
                        <input id="plNamaPemeriksa" type="text" placeholder="Nama pemeriksa"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tgl Pemeriksaan</label>
                        <input id="plTglPeriksa" type="date"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Perlengkapan <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <input id="plJenisInput" type="text" autocomplete="off" placeholder="Cari atau pilih jenis perlengkapan..."
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <ul id="plJenisList" class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-xl border border-slate-700 bg-slate-800 shadow-xl"></ul>
                    </div>
                    <p id="plJenisSmhInfo" class="mt-1 text-xs text-blue-400 hidden"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Saldo (Buku) <span class="text-red-400">*</span></label>
                        <input id="plSaldo" type="number" min="0" value="0"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Fisik <span class="text-red-400">*</span></label>
                        <div class="flex items-center gap-2">
                            <button type="button" id="plFisikMinus"
                                class="rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">−</button>
                            <input id="plFisik" type="number" min="0" value="0"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-center text-sm text-slate-100 outline-none focus:border-blue-500">
                            <button type="button" id="plFisikPlus"
                                class="rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">+</button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Selisih</label>
                        <input id="plSelisih" type="number" readonly value="0"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-bold outline-none"
                            style="color: #94a3b8">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Penjelasan</label>
                    <textarea id="plPenjelasan" rows="2" placeholder="Keterangan jika ada selisih..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" id="plResetBtn"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Reset</button>
                    <button type="button" id="plSimpanBtn"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-500">Simpan</button>
                </div>
            </div>

            {{-- Tabel Data --}}
            <div id="plTableWrap" class="hidden rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800 px-5 py-3">
                    <span class="text-sm font-bold text-slate-200">Daftar Pemeriksaan Perlengkapan</span>
                    <span id="plCount" class="text-xs text-slate-400"></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-800/50 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Jenis Perlengkapan</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3 text-right">Fisik</th>
                                <th class="px-4 py-3 text-right">Selisih</th>
                                <th class="px-4 py-3 text-left">Penjelasan</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="plTableBody" class="divide-y divide-slate-800"></tbody>
                    </table>
                </div>
                {{-- Total --}}
                <div class="border-t border-slate-700 bg-slate-800/30 px-5 py-3 grid grid-cols-3 gap-4 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Saldo</div>
                        <div id="plTotalSaldo" class="font-bold text-slate-200">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Fisik</div>
                        <div id="plTotalFisik" class="font-bold text-slate-200">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Selisih</div>
                        <div id="plTotalSelisih" class="font-bold text-slate-200">0</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Panel: Bank --}}
        <div id="tabPanel-bank" class="audit-tab-panel hidden space-y-5">
            <input type="hidden" id="bankPlanAuditId">

            {{-- Daftar kartu bank (di-generate via JS) --}}
            <div id="bankList" class="space-y-5"></div>

            <button type="button" id="addBankBtn"
                class="w-full rounded-2xl border-2 border-dashed border-blue-400 px-4 py-3 text-sm font-semibold text-blue-500 hover:bg-blue-50/5">
                + Tambah Bank
            </button>

            {{-- Register Cek yang belum digunakan --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🧾 Register Cek/Giro yang Belum Digunakan</div>
                <div class="space-y-3 p-5">
                    <table class="w-full text-sm">
                        <tbody id="registerCekBody"></tbody>
                    </table>
                    <button type="button" data-add="registerCek" class="add-row-btn rounded-lg border border-dashed border-slate-400 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">+ Tambah Register Cek</button>
                </div>
            </div>

            {{-- Aksi simpan --}}
            <div class="flex justify-end gap-3">
                <button type="button" id="saveBankFormBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan Pemeriksaan Bank
                </button>
            </div>
        </div>
    </div>
</section>

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
