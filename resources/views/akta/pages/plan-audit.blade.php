@extends('akta.layouts.app')

@section('title', 'Plan Audit - AKTA IAT')
@section('page_title', 'Plan Audit')
@section('page_description', 'Perencanaan audit, jadwal, cabang, tim, dan status')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-lg font-bold">Daftar Plan Audit</h2>
            <p class="mt-1 text-sm text-slate-400">
                Kelola rencana audit berdasarkan no SPT, cabang, jadwal, kepala tim, dan status.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <input id="planSearch" type="search" placeholder="Cari cabang / no SPT..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 sm:w-64">

            <select id="planStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="running">Running</option>
                <option value="done">Done</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <button id="openCreatePlanButton" type="button"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Tambah Plan
            </button>
        </div>
    </div>

    <div id="planAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Cabang</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Tgl Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tim
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi</th>
                    </tr>
                </thead>

                <tbody id="plansTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat plan audit...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="planModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="planModalTitle" class="text-lg font-bold">Tambah Plan Audit</h3>
                <p class="text-sm text-slate-400">Isi data rencana audit.</p>
            </div>

            <button id="closePlanModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="planForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="planId">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">No SPT</label>
                    <input id="noSpt" type="text" placeholder="Diisi otomatis jika dikosongkan"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder-slate-600 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Jenis Audit</label>
                    <select id="jenisAudit" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="Audit">Audit</option>
                        <option value="Audit Online Kas + Unit SMH">Audit Online Kas + Unit SMH</option>
                        <option value="Audit Online Kas + HGP & AHM Oils">Audit Online Kas + HGP & AHM Oils</option>
                        <option value="Audit Verifikasi HO">Audit Verifikasi HO</option>
                        <option value="Audit Verifikasi Lapangan">Audit Verifikasi Lapangan</option>
                        <option value="Audit Serah Terima Sales Office Head">Audit Serah Terima Sales Office Head</option>
                        <option value="Audit Serah Terima Warehouse">Audit Serah Terima Warehouse</option>
                        <option value="Audit Kas + HGP & AHM Oils">Audit Kas + HGP & AHM Oils</option>
                        <option value="Audit Kas + Unit SMH">Audit Kas + Unit SMH</option>
                        <option value="Audit Kas + BPKB">Audit Kas + BPKB</option>
                        <option value="Audit Serah Terima Partkeeper">Audit Serah Terima Partkeeper</option>
                        <option value="Audit Serah Terima Workshop Head">Audit Serah Terima Workshop Head</option>
                        <option value="Audit PJS SO HEAD">Audit PJS SO HEAD</option>
                        <option value="Audit PJS SO ADH">Audit PJS SO ADH</option>
                        <option value="Audit CHTC">Audit CHTC</option>
                        <option value="Audit PAV/HC3/HOHO MDN">Audit PAV/HC3/HOHO MDN</option>
                        <option value="Faktur">Faktur</option>
                        <option value="Audit Serah Terima Kasir">Audit Serah Terima Kasir</option>
                        <option value="Audit Serah Terima Pos Head">Audit Serah Terima Pos Head</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Tanggal Plan</label>
                    <input id="tglPlan" type="text" readonly
                        class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-400 outline-none">
                    <p class="mt-1 text-xs text-slate-500">Terisi otomatis saat plan dibuat.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Cabang <span class="text-red-400">*</span></label>
                    <select id="cabang" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">-- Pilih Cabang --</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Kepala Tim</label>
                    <select id="kepalaTim"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">-- Pilih Kepala Tim --</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Tim Audit</label>
                    <div id="timContainer"
                        class="max-h-48 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-300">
                        <p class="py-2 text-center text-slate-500">Memuat daftar tim...</p>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Catatan</label>
                    <textarea id="keterangan" rows="3"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelPlanFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    Batal
                </button>

                <button type="submit" id="savePlanButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-plan-audit.js')
@endpush