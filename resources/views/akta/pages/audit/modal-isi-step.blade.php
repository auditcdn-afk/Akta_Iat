{{-- Modal Isi Step Birokrasi --}}
<div id="isiStepModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-base font-bold text-slate-100">Isi Keputusan</h3>
                <p id="isiStepRoleName" class="mt-0.5 text-xs font-semibold text-blue-400"></p>
            </div>
            <button id="isiStepCloseBtn" type="button"
                class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>
        <div class="space-y-4 px-5 py-5">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Tanggal Pengisian <span class="text-red-400">*</span></label>
                <input id="isiStepTgl" type="date"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Isi Keputusan <span class="text-red-400">*</span></label>
                <textarea id="isiStepKonten" rows="6" placeholder="Tuliskan keputusan / tindak lanjut..."
                    class="w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button id="isiStepCloseBtn2" type="button" onclick="document.getElementById('isiStepModal').classList.add('hidden');document.getElementById('isiStepModal').classList.remove('flex');"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button id="isiStepSaveBtn" type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Simpan</button>
            </div>
        </div>
    </div>
</div>
