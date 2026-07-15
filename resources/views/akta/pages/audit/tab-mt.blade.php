        <div id="tabPanel-mt" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan MT</h3>
                <button id="mtSaveBtn" type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                    💾 Simpan
                </button>
            </div>

            {{-- Daftar Mekanik --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Daftar Mekanik</label>
                    <button id="mtAddMekanikBtn" type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-dashed border-blue-500 px-3 py-1.5 text-xs font-semibold text-blue-400 hover:bg-blue-900/30 transition">
                        + Tambah Mekanik
                    </button>
                </div>
                {{-- Chip list rendered by JS --}}
                <div id="mtMekanikList" class="flex flex-wrap gap-2 min-h-[36px]">
                    <p class="text-xs text-slate-500 italic">Belum ada mekanik. Klik "+ Tambah Mekanik".</p>
                </div>
                {{-- Inline add form (hidden by default) --}}
                <div id="mtAddForm" class="hidden flex gap-2 items-center mt-1">
                    <input type="text" id="mtNewMekanikInput" placeholder="Nama mekanik baru..."
                        class="flex-1 rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                    <button id="mtConfirmAddBtn" type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
                        Tambah
                    </button>
                    <button id="mtCancelAddBtn" type="button"
                        class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-400 hover:bg-slate-800 transition">
                        Batal
                    </button>
                </div>
            </div>

            {{-- Jenis selector (shown when a mechanic is selected) --}}
            <div id="mtJenisPanel" class="hidden rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mekanik:</span>
                    <span id="mtActiveMekanikLabel" class="text-sm font-bold text-blue-300"></span>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis <span class="text-red-400">*</span></label>
                    <div class="flex gap-2">
                        <button type="button" data-mt-jenis="baru"
                            class="mt-jenis-btn flex-1 rounded-lg border border-slate-600 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                            Baru
                        </button>
                        <button type="button" data-mt-jenis="lama"
                            class="mt-jenis-btn flex-1 rounded-lg border border-slate-600 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                            Lama
                        </button>
                        <button type="button" data-mt-jenis="fi"
                            class="mt-jenis-btn flex-1 rounded-lg border border-slate-600 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                            FI
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kategori tools --}}
            <div id="mtKategoriWrap" class="space-y-4"></div>

        </div>
