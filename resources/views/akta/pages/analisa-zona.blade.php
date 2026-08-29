@extends('akta.layouts.app')

@section('title', 'Analisa Zona - SIMPAS-IAT')
@section('page_title', 'Analisa Zona')
@section('page_description', 'Skor risiko per unit usaha dari data RKK (kas kecil), ACC (pembiayaan & piutang konsumen), dan LPK (penjualan) — untuk menentukan zona yang perlu sering dikunjungi.')

@push('scripts')
    @vite('resources/js/akta-analisa-zona.js')
@endpush

@section('content')
<section class="space-y-5">

    <div id="azAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div id="azAccessDenied" class="hidden rounded-2xl border border-red-700/40 bg-red-900/10 p-6 text-center">
        <div class="text-3xl mb-2">🔒</div>
        <p class="text-sm font-semibold text-red-300">Akses ditolak</p>
        <p class="mt-1 text-xs text-slate-400">Fitur ini dibatasi untuk tim analisa yang ditunjuk. Hubungi admin kalau merasa perlu akses.</p>
    </div>

    <div id="azContent" class="hidden space-y-5">

        {{-- Upload zip --}}
        <div class="rounded-2xl border border-blue-700/40 bg-blue-900/10 p-5">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-blue-300">📦 Upload Data RKK / ACC / LPK / LHPBK</h3>
            <p class="mb-3 text-xs text-slate-400">Upload file zip berisi file .RKK / .ACC / .LPK / PDF LHPBK dari unit usaha (bisa campur beberapa jenis & beberapa hari sekaligus), atau langsung satu file PDF LHPBK tanpa perlu dizip. File yang sudah pernah diupload otomatis dilewati (tidak dobel).</p>
            <div id="azDropzone"
                class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 text-center transition cursor-pointer hover:border-blue-500">
                <span class="text-3xl">🗂️</span>
                <p class="text-sm text-slate-300">Drag &amp; drop file <span class="font-semibold text-blue-400">.zip</span> atau <span class="font-semibold text-blue-400">.pdf</span> (LHPBK) ke sini, atau</p>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                    📁 Pilih File
                    <input type="file" id="azFileInput" accept=".zip,.pdf" class="hidden">
                </label>
                <p id="azUploadMsg" class="hidden text-sm font-medium"></p>
            </div>
        </div>

        {{-- Periode & aksi --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-700 bg-slate-800/60 p-4">
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-300">Periode</label>
                <input type="month" id="azPeriode" class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
            </div>
            <button id="azRecomputeBtn" type="button" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                🔄 Hitung Ulang Skor
            </button>
        </div>

        {{-- Ranking zona --}}
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🎯 Ranking Zona (Skor Risiko)</span>
                <span id="azScoreCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Zona</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-xs">
                    <thead class="border-b border-slate-700 bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-10">#</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Unit Usaha</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Kas Kecil</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Pembiayaan</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-32">Penjualan &amp; Piutang</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Anomali</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Posisi Kas</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-28">Skor Total</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-24">Detail</th>
                        </tr>
                    </thead>
                    <tbody id="azScoreTableBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>
        </div>

        {{-- Drill-down --}}
        <div id="azDrillDownCard" class="hidden overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🔍 Detail Data Mentah — <span id="azDrillDownZona"></span></span>
                <div class="flex items-center gap-1" id="azDrillDownTabs">
                    <button type="button" data-az-jenis="rkk" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Kas Kecil (RKK)</button>
                    <button type="button" data-az-jenis="acc-consumers" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Konsumen (ACC)</button>
                    <button type="button" data-az-jenis="acc-contracts" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Kontrak (ACC)</button>
                    <button type="button" data-az-jenis="acc-receivables" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Piutang (ACC)</button>
                    <button type="button" data-az-jenis="lpk" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Penjualan (LPK)</button>
                    <button type="button" data-az-jenis="posisi-kas" class="az-dd-tab rounded-lg px-3 py-1.5 text-xs font-semibold">Posisi Kas (LHPBK)</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-xs">
                    <thead id="azDrillDownHead" class="border-b border-slate-700 bg-slate-800"></thead>
                    <tbody id="azDrillDownBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>
            <div id="azDrillDownPaging" class="flex items-center justify-between px-5 py-3 text-xs text-slate-400"></div>
        </div>

        {{-- Riwayat upload --}}
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🗒️ Riwayat Upload</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-xs">
                    <thead class="border-b border-slate-700 bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">File</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-20">Jenis</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">Unit Usaha</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-24">Tanggal</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-20">Baris</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">Diupload Oleh</th>
                        </tr>
                    </thead>
                    <tbody id="azUploadsTableBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>
        </div>

    </div>
</section>
@endsection
