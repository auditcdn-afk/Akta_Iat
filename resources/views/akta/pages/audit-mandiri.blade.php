@extends('akta.layouts.app')

@section('title', 'Audit Mandiri - AKTA IAT')
@section('page_title', 'Audit Mandiri')
@section('page_description', 'Modul BU Performance, pengecekan audit mandiri, dan sertijab.')

@section('content')
<section class="space-y-5">
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <div>
            <h2 class="text-lg font-bold">Buat Plan Audit Mandiri</h2>
            <p class="mt-1 text-sm text-slate-400">Nomor plan dibuat otomatis: urutan/tgl/bln/thn/JenisAudit-identitas.</p>
        </div>

        <div id="amAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

        <form id="amForm" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Pemeriksaan <span class="text-red-400">*</span></label>
                <div class="grid grid-cols-2 gap-2 sm:max-w-md">
                    <button type="button" data-value="audit_mandiri"
                        class="am-jenis-pemeriksaan-btn rounded-xl border px-4 py-2 text-sm font-semibold transition bg-blue-600 border-blue-600 text-white">
                        Audit Mandiri
                    </button>
                    <button type="button" data-value="sertijab"
                        class="am-jenis-pemeriksaan-btn rounded-xl border px-4 py-2 text-sm font-semibold transition border-slate-700 text-slate-300 hover:bg-slate-800">
                        Sertijab
                    </button>
                </div>
                <input type="hidden" id="amJenisPemeriksaan" value="audit_mandiri">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="amJenisAudit" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Audit <span class="text-red-400">*</span></label>
                    <select id="amJenisAudit" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">— Pilih Jenis Audit —</option>
                        <option value="SMH">SMH</option>
                        <option value="Sparepart">Sparepart</option>
                        <option value="KAS">KAS</option>
                        <option value="BPKB">BPKB</option>
                        <option value="MT">MT</option>
                    </select>
                </div>
                <div>
                    <label for="amTglPlan" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Plan</label>
                    <input id="amTglPlan" type="date"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label for="amCabang" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang / Unit Usaha</label>
                    <input id="amCabang" type="text" placeholder="Contoh: SO ALB"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label for="amCabangArea" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Wilayah</label>
                    <input id="amCabangArea" type="text" placeholder="Contoh: Aceh"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label for="amCatatan" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan</label>
                <textarea id="amCatatan" rows="2"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                    Buat Plan
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-800 p-5">
            <h3 class="font-bold text-slate-100">Daftar Plan Audit Mandiri</h3>
            <input id="amSearch" type="search" placeholder="Cari no plan / cabang / jenis audit..."
                class="w-72 rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Pemeriksaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tgl Plan</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="amTableBody" class="divide-y divide-slate-800">
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    @vite('resources/js/akta-audit-mandiri.js')
@endpush
