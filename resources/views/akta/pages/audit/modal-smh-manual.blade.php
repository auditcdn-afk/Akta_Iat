        <div id="smhManualModal" class="fixed inset-0 z-[999] hidden items-center justify-center bg-black/60">
            <div class="w-full max-w-sm rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-bold text-slate-100">➕ Tambah Manual Unit SMH</h4>
                    <button id="smhManualClose" class="text-slate-400 hover:text-slate-200 text-lg leading-none">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-300">No. Mesin <span class="text-red-400">*</span></label>
                        <input id="smhManualNoMesin" type="text" placeholder="Contoh: JMH1E1234567"
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-emerald-500 uppercase">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-300">No. Rangka <span class="text-red-400">*</span></label>
                        <input id="smhManualNoRangka" type="text" placeholder="Contoh: MH1JM112X5K123456"
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-emerald-500 uppercase">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-300">Gudang</label>
                        <input id="smhManualGudang" type="text" placeholder="Contoh: SO, MH, POS LW"
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-emerald-500">
                    </div>
                </div>
                <div id="smhManualAlert" class="hidden rounded-lg px-3 py-2 text-xs"></div>
                <div class="flex gap-3 pt-1">
                    <button id="smhManualSave"
                        class="flex-1 rounded-xl bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Simpan
                    </button>
                    <button id="smhManualCancel"
                        class="flex-1 rounded-xl border border-slate-600 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                        Batal
                    </button>
                </div>
            </div>
        </div>
