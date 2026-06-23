@extends('akta.layouts.app')

@section('title', 'Task - AKTA IAT')
@section('page_title', 'Tugas Audit')
@section('page_description', 'Plan audit yang ditugaskan kepada Anda — rekam pelaksanaan audit')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Tugas Audit Saya</h2>
            <p class="mt-1 text-sm text-slate-400">
                Task hanya tempat persinggahan kegiatan. Setelah kegiatan selesai (atau sudah disetujui), task otomatis hilang dari daftar ini.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="taskSearch" type="search" placeholder="Cari task / cabang / no SPT..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-64">

            <select id="taskStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status</option>
                <option value="todo">Belum Dikerjakan</option>
                <option value="in_progress">Sedang Berjalan</option>
            </select>
        </div>
    </div>

    <div id="taskAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Plan / Cabang</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Audit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tgl Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Pelaksanaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>

                <tbody id="tasksTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat tugas audit...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="taskModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="taskModalTitle" class="text-lg font-bold">Pelaksanaan Audit</h3>
                <p class="text-sm text-slate-400">Lengkapi waktu Mulai & Selesai audit, lampiran opsional.</p>
            </div>

            <button id="closeTaskModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="taskForm" class="space-y-5 px-5 py-5">
            <input type="hidden" id="taskId">
            <input type="hidden" id="approvePlanId">

            {{-- ── Data Plan (read-only) ── --}}
            <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Data Plan Audit</h4>
                <dl id="planDetail" class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm"></dl>
            </div>

            {{-- ── Riwayat Status Birokrasi ── --}}
            <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Riwayat Status Birokrasi</h4>
                <ol id="planTimeline" class="space-y-3 text-sm"></ol>
            </div>

            {{-- ── Form Pelaksanaan (auditor / admin / manajer) ── --}}
            <div id="execSection" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">
                            Mulai Audit <span class="text-red-400">*</span>
                        </label>
                        <input id="startedAt" type="date" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">
                            Selesai Audit <span class="text-red-400">*</span>
                        </label>
                        <input id="finishedAt" type="date" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-300">File Lampiran (opsional)</label>
                        <input id="lampiran" type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-white outline-none focus:border-blue-500">
                        <p class="mt-1 text-xs text-slate-500">PDF, gambar, Excel, atau Word. Maks 10 MB.</p>
                        <p id="currentLampiran" class="mt-2 hidden text-xs text-slate-400"></p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                    <button type="button" id="cancelTaskFormButton"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                        Batal
                    </button>

                    <button type="submit" id="saveTaskButton"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        Simpan Pelaksanaan
                    </button>
                </div>
            </div>

            {{-- ── Approval (koordinator) ── --}}
            <div id="approvalSection" class="hidden">
                <div id="approvalInfo" class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-amber-200">
                    Plan audit ini menunggu persetujuan. Periksa data plan di atas lalu pilih tindakan.
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-800 pt-4 mt-4">
                    <button type="button" id="cancelTaskFormButton2"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                        Tutup
                    </button>
                    <button type="button" id="rejectBtn"
                        class="rounded-xl border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/10">
                        Tolak
                    </button>
                    <button type="button" id="approveBtn"
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Setujui
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-task.js')
@endpush
