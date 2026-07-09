@extends('akta.layouts.app')

@section('title', 'Report Audit - AKTA IAT')
@section('page_title', 'Report Audit')
@section('page_description', 'Ringkasan laporan audit berdasarkan Plan Audit, Task, Rekomendasi, PICA, dan SK')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Report Audit</h2>
            <p class="mt-1 text-sm text-slate-400">
                Lihat progres audit per Plan Audit berdasarkan Task, Rekomendasi, PICA, dan SK.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="reportAuditSearch" type="search" placeholder="Cari no SPT / cabang / unit usaha..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-80">

            <select id="reportAuditStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status Plan</option>
                <option value="draft">Draft</option>
                <option value="open">Open</option>
                <option value="progress">Progress</option>
                <option value="in_progress">In Progress</option>
                <option value="done">Done</option>
                <option value="selesai">Selesai</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <button id="reloadReportAuditButton" type="button"
                class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                Refresh
            </button>
        </div>
    </div>

    <div id="reportAuditAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plan Audit</div>
            <div id="reportPlanTotalStat" class="mt-2 text-2xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Task</div>
            <div id="reportTaskTotalStat" class="mt-2 text-2xl font-bold text-blue-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rekomendasi</div>
            <div id="reportRecommendationTotalStat" class="mt-2 text-2xl font-bold text-amber-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">PICA Closed</div>
            <div id="reportPicaClosedStat" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">PICA Total</div>
            <div id="reportPicaTotalStat" class="mt-2 text-xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">SK Total</div>
            <div id="reportSkTotalStat" class="mt-2 text-xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">SK Selesai</div>
            <div id="reportSkSelesaiStat" class="mt-2 text-xl font-bold text-emerald-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Generated</div>
            <div id="reportGeneratedAtStat" class="mt-2 text-sm font-semibold text-slate-300">-</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Plan Audit
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Task
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Rekomendasi
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            PICA
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            SK
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Progress
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody id="reportAuditTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat Report Audit...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="reportAuditDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="reportAuditDetailTitle" class="text-lg font-bold">Detail Report Audit</h3>
                <p id="reportAuditDetailSubtitle" class="text-sm text-slate-400">-</p>
            </div>

            <button id="closeReportAuditDetailButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <div class="space-y-5 px-5 py-5">
            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</div>
                    <div id="detailCompletionPercent" class="mt-2 text-2xl font-bold text-blue-300">0%</div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Task Done</div>
                    <div id="detailTaskDone" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">PICA Closed</div>
                    <div id="detailPicaClosed" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">SK Selesai</div>
                    <div id="detailSkSelesai" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <h4 class="font-bold text-slate-100">Plan Audit</h4>
                <div id="detailPlanInfo" class="mt-3 grid gap-3 text-sm text-slate-300 md:grid-cols-3"></div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <h4 class="font-bold text-slate-100">Task</h4>
                    <div id="detailTasks" class="mt-3 space-y-3"></div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <h4 class="font-bold text-slate-100">Rekomendasi</h4>
                    <div id="detailRecommendations" class="mt-3 space-y-3"></div>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <h4 class="font-bold text-slate-100">PICA</h4>
                    <div id="detailPicas" class="mt-3 space-y-3"></div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <h4 class="font-bold text-slate-100">Surat Keputusan</h4>
                    <div id="detailSuratKeputusan" class="mt-3 space-y-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="penilaianModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Penilaian Plan Audit</h3>
                <p class="text-sm text-slate-400">Penilaian dari koordinator/manajer setelah pemeriksaan selesai.</p>
            </div>
            <button type="button" id="closePenilaianBtn"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>

        <div class="space-y-4 px-5 py-5">
            <input type="hidden" id="penilaianPlanId">

            <p id="penilaianLoading" class="text-sm text-slate-400">Memuat...</p>

            <div id="penilaianViewWrap" class="hidden space-y-3">
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-200">
                    Penilaian Anda untuk plan ini sudah tersimpan.
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Tgl Pemeriksaan</label>
                    <p id="penilaianViewTgl" class="text-sm text-slate-200">-</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Hasil Penilaian</label>
                    <p id="penilaianViewHasil" class="text-sm font-bold">-</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Catatan Penilaian</label>
                    <p id="penilaianViewCatatan" class="whitespace-pre-wrap rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">-</p>
                </div>
            </div>

            <form id="penilaianForm" class="hidden space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Tgl Pemeriksaan</label>
                    <p id="penilaianFormTgl" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-400">-</p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Hasil Penilaian <span class="text-red-400">*</span></label>
                    <div class="flex gap-3">
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-300 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300">
                            <input type="radio" name="penilaianHasil" value="ok" class="penilaian-hasil-radio">
                            OK
                        </label>
                        <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-300 has-[:checked]:border-red-500 has-[:checked]:bg-red-500/10 has-[:checked]:text-red-300">
                            <input type="radio" name="penilaianHasil" value="not_ok" class="penilaian-hasil-radio">
                            Not OK
                        </label>
                    </div>
                </div>
                <div id="penilaianCatatanWrap" class="hidden">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Catatan Penilaian <span class="text-red-400">*</span></label>
                    <textarea id="penilaianCatatan" rows="5"
                        class="w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                    <button type="button" id="cancelPenilaianBtn"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                    <button type="submit" id="savePenilaianBtn"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-report-audit.js')
@endpush
