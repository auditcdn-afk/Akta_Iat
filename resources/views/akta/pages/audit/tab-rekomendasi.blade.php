        <div id="tabPanel-rekomendasi" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Rekomendasi Audit</h3>
                <button id="rekomendasiTambahBtn" type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    + Tambah Rekomendasi
                </button>
            </div>

            {{-- Alert --}}
            <div id="rekomendasiAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

            {{-- Form tambah / edit --}}
            <div id="rekomendasiForm" class="hidden rounded-2xl border border-slate-700 bg-slate-800/60 p-5 space-y-4">
                <h4 class="text-sm font-bold text-slate-200">Rekomendasi Form</h4>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-blue-400">Audit ID <span class="text-red-400">*</span></label>
                        <input id="rekomendasiNoSpt" type="text" readonly
                            class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-2 text-sm text-slate-300 outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-blue-400">Nama Unit Usaha</label>
                        <input id="rekomendasiCabang" type="text" readonly
                            class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-2 text-sm text-slate-300 outline-none cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-blue-400">Tanggal Audit</label>
                    <input id="rekomendasiTglAudit" type="date"
                        class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-semibold text-blue-400">Isi Rekomendasi <span class="text-red-400">*</span></label>
                        <span class="text-xs text-slate-500 italic">Auto-filled dari data pemeriksaan · dapat diedit</span>
                    </div>
                    <textarea id="rekomendasiIsi" rows="14" placeholder="Tuliskan rekomendasi..."
                        class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 text-xs text-slate-100 outline-none focus:border-blue-500 resize-y font-mono leading-relaxed"></textarea>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-800/40 px-4 py-2">
                    <span class="text-lg">📎</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-blue-400">Upload File Lampiran</p>
                        <p id="rekomendasiFileName" class="text-xs text-blue-300 truncate hidden"></p>
                        <p class="text-xs text-slate-500" id="rekomendasiFileHint">PDF, JPG, PNG, DOC (opsional)</p>
                    </div>
                    <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                        Pilih File
                        <input id="rekomendasiFileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="hidden">
                    </label>
                    <div id="rekomendasiDropzone" class="hidden"></div>
                </div>

                <div class="flex gap-3 justify-end pt-2">
                    <button id="rekomendasiBatalBtn" type="button"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition">
                        Cancel
                    </button>
                    <button id="rekomendasiSimpanBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
                        Save
                    </button>
                </div>
            </div>

            {{-- Daftar rekomendasi --}}
            <div id="rekomendasiList" class="space-y-3">
                <p class="py-8 text-center text-sm text-slate-500">Belum ada rekomendasi untuk pemeriksaan ini.</p>
            </div>

        </div>
