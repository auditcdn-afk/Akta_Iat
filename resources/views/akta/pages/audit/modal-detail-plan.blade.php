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
