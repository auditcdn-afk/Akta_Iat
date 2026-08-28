@extends('akta.layouts.app')

@section('title', 'Upload Data Analisa - SIMPAS-IAT')
@section('page_title', 'Upload Data Analisa')
@section('page_description', 'Upload file harian .RKK / .ACC / .LPK dari unit usaha Anda (dalam 1 file zip) untuk keperluan analisa zona oleh tim internal audit.')

@push('scripts')
    @vite('resources/js/akta-upload-analisa.js')
@endpush

@section('content')
<section class="space-y-5">

    <div id="uaAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="rounded-2xl border border-blue-700/40 bg-blue-900/10 p-5">
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-blue-300">📦 Upload Data RKK / ACC / LPK</h3>
        <p class="mb-3 text-xs text-slate-400">
            Upload 1 file zip berisi file <span class="text-blue-300 font-semibold">.RKK</span> / <span class="text-blue-300 font-semibold">.ACC</span> / <span class="text-blue-300 font-semibold">.LPK</span> (bisa campur beberapa jenis & beberapa hari sekaligus) dari sistem unit usaha Anda.
            Data ini dipakai tim internal audit untuk analisa zona — file yang sudah pernah diupload otomatis dilewati (tidak dobel), dan hanya file dengan kode unit usaha yang cocok dengan akun Anda yang akan diproses.
        </p>
        <div id="uaDropzone"
            class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition cursor-pointer hover:border-blue-500">
            <span class="text-4xl">🗂️</span>
            <p class="text-sm text-slate-300">Drag &amp; drop file <span class="font-semibold text-blue-400">.zip</span> ke sini, atau</p>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                📁 Pilih File Zip
                <input type="file" id="uaFileInput" accept=".zip" class="hidden">
            </label>
            <p id="uaUploadMsg" class="hidden text-sm font-medium"></p>
        </div>
    </div>

    <div id="uaResultCard" class="hidden rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-3">
        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-300">Hasil Upload Terakhir</h4>
        <div id="uaResultBody" class="space-y-2 text-xs"></div>
    </div>

    {{-- Riwayat upload unit usaha sendiri — supaya jelas file apa yang
         sudah pernah masuk, tanpa harus coba upload ulang cuma untuk tahu. --}}
    <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🗒️ Riwayat Upload Anda</span>
            <span id="uaHistoryCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 File</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-xs">
                <thead class="border-b border-slate-700 bg-slate-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">File</th>
                        <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-20">Jenis</th>
                        <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-24">Tanggal Data</th>
                        <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-20">Baris</th>
                        <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-32">Diupload Oleh</th>
                    </tr>
                </thead>
                <tbody id="uaHistoryTableBody" class="divide-y divide-slate-800/60"></tbody>
            </table>
        </div>
    </div>

</section>
@endsection
