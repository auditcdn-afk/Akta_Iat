@extends('akta.layouts.app')

@section('title', 'Pinjaman BPK & BPB - SIMPAS-IAT')
@section('page_title', 'Pinjaman BPK & BPB')
@section('page_description', 'Pengajuan pinjaman cabang lintas plan — persetujuan, penolakan, dan riwayatnya')

@section('content')
<section class="space-y-5">

    {{-- Giliran saya: yang paling mendesak, jadi diletakkan paling atas.
         Halaman Task menyaring tugas berdasarkan status PLAN, sehingga pengajuan
         yang menunggu Koordinator pada plan yang sudah berjalan tidak pernah
         muncul di sana. Kotak ini memakai status PENGAJUAN, jadi tidak ada yang
         terlewat. --}}
    <div id="pinjamanGiliranBox" class="hidden rounded-2xl border border-amber-500/40 bg-amber-500/5 p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-amber-300">Menunggu Persetujuan Anda</h2>
                <p id="pinjamanGiliranInfo" class="mt-1 text-sm text-slate-400"></p>
            </div>
            <span id="pinjamanGiliranJumlah"
                class="shrink-0 rounded-full bg-amber-500/20 px-3 py-1 text-sm font-bold text-amber-200">0</span>
        </div>
        <div id="pinjamanGiliranList" class="mt-4 grid gap-3 lg:grid-cols-2"></div>
    </div>

    <div class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Daftar Pengajuan</h2>
            <p id="pinjamanCakupan" class="mt-1 text-sm text-slate-400">Memuat...</p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
            <input id="pinjamanCari" type="search" placeholder="Cari cabang / No SPD / pengaju..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-64">

            <select id="pinjamanFilterJenis"
                class="min-w-[9.5rem] rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Jenis</option>
                <option value="BPK">BPK</option>
                <option value="BPB">BPB</option>
            </select>

            <select id="pinjamanFilterStatus"
                class="min-w-[13rem] rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status</option>
                <option value="pending_koordinator">Menunggu Koordinator</option>
                <option value="pending_manajer">Menunggu Manajer Audit</option>
                <option value="pending_coo">Menunggu COO</option>
                <option value="pending_unit">Menunggu Unit Usaha</option>
                <option value="pending_bpk">Menunggu Role BPK</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
            </select>

            <div class="flex w-full items-center gap-2 lg:w-auto">
                <input id="pinjamanDari" type="date" title="Diajukan dari tanggal"
                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:flex-none">
                <span class="shrink-0 text-xs text-slate-500">s/d</span>
                <input id="pinjamanSampai" type="date" title="Diajukan sampai tanggal"
                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:flex-none">
            </div>
        </div>
    </div>

    <div id="pinjamanAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div id="pinjamanRingkas" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"></div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis / Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang Realisasi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No SPD</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Pengaju</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>

                <tbody id="pinjamanTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">Memuat pengajuan...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- Modal riwayat persetujuan --}}
<div id="pinjamanJejakModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="pinjamanJejakJudul" class="text-lg font-bold">Riwayat Persetujuan</h3>
                <p id="pinjamanJejakSub" class="text-sm text-slate-400"></p>
            </div>
            <button id="pinjamanJejakTutup" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>
        <div id="pinjamanJejakIsi" class="space-y-3 px-5 py-5"></div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-pinjaman.js')
@endpush
