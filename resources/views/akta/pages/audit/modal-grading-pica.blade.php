{{-- Modal: PICA per item grading (di luar semua panel agar fixed benar-benar ke viewport) --}}
<div id="gradingPicaModal" class="hidden fixed inset-0 z-[999] flex items-end sm:items-center justify-center bg-black/60">
    <div class="w-full max-w-lg bg-slate-900 rounded-t-2xl sm:rounded-2xl border border-slate-700 shadow-2xl p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold text-slate-100">🎯 PICA — Current Condition</h4>
            <button onclick="gradingClosePicaModal()" class="text-slate-400 hover:text-slate-200 text-lg leading-none">✕</button>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800/60 p-3 space-y-1 text-sm">
            <div class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Pemeriksaan</div>
            <div id="gradingPicaNama" class="text-slate-100 font-medium"></div>
            <div class="text-slate-400 text-xs font-semibold uppercase tracking-wide mt-2">Hasil</div>
            <div id="gradingPicaHasil" class="text-amber-300 font-semibold"></div>
            <div class="text-slate-400 text-xs font-semibold uppercase tracking-wide mt-2">Nilai</div>
            <div id="gradingPicaNilai" class="text-yellow-300 font-mono font-bold"></div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Current Condition <span class="text-red-400">*</span></label>
            <textarea id="gradingPicaCondition" rows="4"
                placeholder="Deskripsikan kondisi temuan / permasalahan yang ditemukan..."
                class="w-full rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:border-blue-400 focus:outline-none resize-y"></textarea>
        </div>
        <div class="flex gap-2 justify-end">
            <button onclick="gradingClosePicaModal()"
                class="rounded-xl border border-slate-600 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition">Batal</button>
            <button onclick="gradingSavePicaModal()"
                class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">💾 Simpan</button>
        </div>
    </div>
</div>
