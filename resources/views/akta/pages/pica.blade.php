@extends('akta.layouts.app')

@section('title', 'PICA - AKTA IAT')
@section('page_title', 'PICA')
@section('page_description', 'Tindak lanjut operasional dari rekomendasi audit')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Daftar PICA</h2>
            <p class="mt-1 text-sm text-slate-400">
                Kelola problem, root cause, corrective action, preventive action, PIC, target, dan status tindak lanjut.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="picaSearch" type="search" placeholder="Cari PICA / problem / PIC..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-72">

            <select id="picaStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status</option>
                <option value="open">Open</option>
                <option value="progress">Progress</option>
                <option value="closed">Closed</option>
            </select>

            <select id="picaPriorityFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Prioritas</option>
                <option value="rendah">Rendah</option>
                <option value="sedang">Sedang</option>
                <option value="tinggi">Tinggi</option>
                <option value="kritis">Kritis</option>
            </select>

            <button id="syncPicaBtn" type="button"
                class="rounded-xl border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/10">
                🔄 Sinkron dari Grading
            </button>

            <button id="openCreatePicaButton" type="button"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Tambah
            </button>
        </div>
    </div>

    <div id="picaAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</div>
            <div id="picaTotalStat" class="mt-2 text-2xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open</div>
            <div id="picaOpenStat" class="mt-2 text-2xl font-bold text-blue-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</div>
            <div id="picaProgressStat" class="mt-2 text-2xl font-bold text-amber-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Closed</div>
            <div id="picaClosedStat" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
        <label class="mb-2 block text-sm font-medium text-slate-300">Filter Rekomendasi</label>
        <select id="picaRecommendationFilter"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Rekomendasi</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            PICA</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Rekomendasi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            PIC</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Target</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Prioritas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi</th>
                    </tr>
                </thead>

                <tbody id="picasTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat PICA...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="picaModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="picaModalTitle" class="text-lg font-bold">Tambah PICA</h3>
                <p class="text-sm text-slate-400">PICA wajib terhubung ke rekomendasi audit.</p>
            </div>

            <button id="closePicaModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="picaForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="picaId">
            <input type="hidden" id="auditRecommendationId">
            <input type="hidden" id="picaNo">

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Judul</label>
                    <input id="title" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                {{-- Current Condition: diisi auditor, read-only untuk cabang --}}
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Current Condition <span class="text-xs text-slate-500">(diisi auditor)</span></label>
                    <textarea id="currentCondition" rows="2"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none focus:border-blue-500 disabled:opacity-50"></textarea>
                </div>

                {{-- Problem Identification: read-only, diisi cabang --}}
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-400">Problem Identification <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <textarea id="problemIdentificationReadonly" rows="3" disabled
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none opacity-70 cursor-not-allowed"></textarea>
                </div>

                {{-- Corrective Action: read-only, diisi cabang --}}
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-400">Corrective Action <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <textarea id="correctiveAction" rows="3" disabled
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none opacity-70 cursor-not-allowed"></textarea>
                </div>

                {{-- PIC Completion: read-only --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-400">PIC Completion <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <input id="pic" type="text" disabled
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none opacity-70 cursor-not-allowed">
                </div>

                {{-- Relation Ship 1: read-only --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-400">Relation Ship <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <input id="relationShip" type="text" disabled
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none opacity-70 cursor-not-allowed">
                    <datalist id="userDatalist1"></datalist>
                </div>

                {{-- Relation Ship 2: read-only --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-400">Relation Ship 2 <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <input id="relationShip2" type="text" disabled
                        class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-300 outline-none opacity-70 cursor-not-allowed">
                    <datalist id="userDatalist2"></datalist>
                </div>

                {{-- Tanggapan PICA: diisi forwarded party --}}
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-amber-300">Tanggapan PICA <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <textarea id="problemIdentification" rows="3"
                        class="w-full rounded-xl border border-amber-500/30 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-amber-500"></textarea>
                </div>

                {{-- Deadline Completion: diisi cabang --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-amber-300">Deadline Completion <span class="text-xs text-slate-500">(diisi cabang)</span></label>
                    <input id="targetDate" type="date"
                        class="w-full rounded-xl border border-amber-500/30 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-amber-500">
                </div>

                <input type="hidden" id="priority" value="sedang">
                <input type="hidden" id="status" value="open">
                <input type="hidden" id="actualDate">

                <input type="hidden" id="notes">

                {{-- Unit Usaha: hanya admin/manajer yang bisa mengubah --}}
                <div id="unitUsahaWrap" class="sm:col-span-2 hidden">
                    <label class="mb-1 block text-sm font-medium text-indigo-300">Unit Usaha (Cabang) <span class="text-xs text-slate-500">— hanya admin</span></label>
                    <input id="unitUsaha" type="text" placeholder="Contoh: POS PJD"
                        class="w-full rounded-xl border border-indigo-500/40 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500">
                    <p class="mt-1 text-xs text-slate-500">Unit usaha ini menentukan cabang mana yang dapat melihat PICA.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelPicaFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    Batal
                </button>

                <button type="submit" id="savePicaButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-pica.js')
@endpush
