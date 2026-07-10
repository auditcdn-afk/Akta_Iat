@extends('akta.layouts.app')

@section('title', 'Mobil Dinas - AKTA IAT')
@section('page_title', 'Mobil Dinas')
@section('page_description', 'Pengajuan penggunaan mobil dinas: auditor ajukan, manajer setujui, MRR lengkapi data kendaraan')

@push('scripts')
    @vite('resources/js/akta-mobil-dinas.js')
@endpush

@section('content')
<section class="space-y-5">

    <div id="mdAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Form Pengajuan (auditor/admin) --}}
    <div id="mdFormCard" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-200">
            <span>🚗</span> Form Pengajuan Mobil Dinas
        </h3>

        <form id="mdForm" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Supir Request <span class="text-red-400">*</span></label>
                    <input id="mdSupirRequest" type="text" required placeholder="Nama supir yang diminta"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Berangkat <span class="text-red-400">*</span></label>
                    <input id="mdTglBerangkat" type="date" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Pulang <span class="text-red-400">*</span></label>
                    <input id="mdTglPulang" type="date" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">PIC Mobil <span class="text-red-400">*</span> <span class="normal-case font-normal text-slate-500">(bisa lebih dari satu, dari daftar Auditor)</span></label>
                    <div id="mdPicOptions" class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 p-3">
                        <p class="text-sm text-slate-500">Memuat daftar PIC...</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Upload SPD PIC <span class="text-red-400">*</span> <span class="normal-case font-normal text-slate-500">(gambar/PDF, wajib)</span></label>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">
                        <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                            Pilih File
                            <input id="mdFileInput" type="file" accept=".jpg,.jpeg,.png,.pdf" required class="hidden">
                        </label>
                        <span id="mdFileName" class="truncate text-sm text-slate-400">Belum ada file</span>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Alur birokrasi: <span class="text-slate-300 font-semibold">Auditor mengajukan</span> →
                        <span class="text-slate-300 font-semibold">Manajer Audit menyetujui/menolak</span> →
                        <span class="text-slate-300 font-semibold">MRR mengirim form (nama supir, plat, jenis mobil)</span>.
                    </p>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-800 pt-4">
                <button type="submit" id="mdSaveBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    📨 Ajukan
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Supir Request</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">PIC Mobil</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">SPD</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Data Kendaraan</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="mdTableBody" class="divide-y divide-slate-800 text-slate-200">
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- Modal Approve/Reject (manajer) --}}
<div id="mdDecideModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="text-sm font-bold text-slate-200">Keputusan Pengajuan Mobil Dinas</h3>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan (opsional)</label>
            <textarea id="mdDecideCatatan" rows="3" placeholder="Catatan manajer..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
        </div>
        <div class="flex justify-end gap-2">
            <button id="mdDecideCancelBtn" type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">Batal</button>
            <button id="mdRejectBtn" type="button" class="rounded-xl border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10 transition">Tolak</button>
            <button id="mdApproveBtn" type="button" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition">Setujui</button>
        </div>
    </div>
</div>

{{-- Modal Lengkapi Form (MRR) --}}
<div id="mdCompleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="text-sm font-bold text-slate-200">Lengkapi Data Kendaraan (MRR)</h3>
        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Supir <span class="text-red-400">*</span></label>
                <input id="mdNamaSupir" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Plat Mobil <span class="text-red-400">*</span></label>
                <input id="mdPlatMobil" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Mobil <span class="text-red-400">*</span></label>
                <input id="mdJenisMobil" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button id="mdCompleteCancelBtn" type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">Batal</button>
            <button id="mdCompleteSubmitBtn" type="button" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Kirim Form</button>
        </div>
    </div>
</div>
@endsection
