        <div id="tabPanel-grading" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">⭐ Grading Audit</h3>
                <button id="gradingSaveBtn" type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                    💾 Simpan Grading
                </button>
            </div>

            {{-- Form Header Grading --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-5 space-y-5">
                <h4 class="text-sm font-bold uppercase tracking-wide text-blue-300">📋 Data Grading</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- ID Grading (tanggal) --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">ID Grading <span class="text-red-400">*</span></label>
                        <input type="date" id="gradingIdGrading"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    {{-- Area (auto dari db_unit_usaha) --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Area / Wilayah
                            <span class="ml-1 font-normal text-slate-500">(otomatis dari unit usaha)</span>
                        </label>
                        <input type="text" id="gradingArea" readonly
                            class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-200 cursor-default select-all">
                        <p id="gradingAreaInfo" class="text-xs text-slate-500"></p>
                    </div>
                </div>

                {{-- Jenis --}}
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-semibold text-slate-300">Jenis</label>
                    <div id="gradingJenisBtns" class="flex flex-wrap gap-2">
                        @foreach(['Cabang','Bengkel','WHS PART','WHS UNIT','Lain-Lain'] as $j)
                        <button type="button" data-grading-jenis="{{ $j }}"
                            class="grading-jenis-btn rounded-xl border border-slate-600 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-300 hover:border-blue-400 hover:text-blue-300 transition">
                            {{ $j }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- BBNKB & Fraud --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-slate-300">BBNKB?</label>
                        <div class="flex gap-2">
                            <button type="button" data-grading-bbnkb="N"
                                class="grading-bbnkb-btn flex-1 rounded-xl border border-slate-600 bg-slate-800 py-2 text-sm font-bold text-slate-300 hover:border-slate-400 transition">N</button>
                            <button type="button" data-grading-bbnkb="Y"
                                class="grading-bbnkb-btn flex-1 rounded-xl border border-slate-600 bg-slate-800 py-2 text-sm font-bold text-slate-300 hover:border-blue-400 transition">Y</button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold text-slate-300">Fraud?</label>
                        <div class="flex gap-2">
                            <button type="button" data-grading-fraud="N"
                                class="grading-fraud-btn flex-1 rounded-xl border border-slate-600 bg-slate-800 py-2 text-sm font-bold text-slate-300 hover:border-slate-400 transition">N</button>
                            <button type="button" data-grading-fraud="Y"
                                class="grading-fraud-btn flex-1 rounded-xl border border-slate-600 bg-slate-800 py-2 text-sm font-bold text-red-400 hover:border-red-400 transition">Y</button>
                        </div>
                    </div>
                </div>

                {{-- Fraud Detail (hidden by default) --}}
                <div id="gradingFraudDetail" class="hidden rounded-xl border border-red-700/40 bg-red-900/10 p-4 space-y-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-red-300">Jenis Fraud</label>
                        <div id="gradingJenisFraudTags" class="flex flex-wrap gap-2 min-h-8"></div>
                        <div class="flex gap-2 mt-1">
                            <input type="text" id="gradingJenisFraudInput" placeholder="Tulis jenis fraud, tekan Enter..."
                                class="flex-1 rounded-lg border border-red-700/50 bg-slate-900 px-3 py-2 text-xs text-slate-100 focus:border-red-400 focus:outline-none">
                            <button type="button" id="gradingJenisFraudAdd"
                                class="rounded-lg bg-red-700/40 px-3 py-2 text-xs text-red-300 hover:bg-red-700/60 transition">+ Tambah</button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-red-300">Keterangan Fraud</label>
                        <textarea id="gradingKeteranganFraud" rows="2" placeholder="Keterangan fraud..."
                            class="rounded-lg border border-red-700/50 bg-slate-900 px-3 py-2 text-xs text-slate-100 focus:border-red-400 focus:outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            {{-- Stat total nilai --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="gradingStatItem">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Item Pemeriksaan</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-400" id="gradingStatNilai">0.00</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Total Nilai</div>
                </div>
            </div>

            {{-- Tabel Detail Grading --}}
            <div class="overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                    <span class="text-sm font-bold text-slate-200">⭐ Detail Pemeriksaan Grading</span>
                    <button id="gradingAddDetailBtn" type="button"
                        class="rounded-xl bg-emerald-700/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-700/60 transition">
                        + Tambah Item
                    </button>
                </div>
                <table class="w-full min-w-[700px] text-xs">
                    <thead class="border-b border-slate-700 bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-8">No.</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama Pemeriksaan</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-72">Hasil Pemeriksaan</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-20">Nilai</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-16">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="gradingDetailBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>

            {{-- Modal tambah/edit detail --}}
            <div id="gradingDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 space-y-4 shadow-2xl mx-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold text-slate-100" id="gradingDetailModalTitle">Tambah Item Pemeriksaan</h4>
                        <button id="gradingDetailModalClose" type="button" class="text-slate-400 hover:text-slate-200">✕</button>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Nama Pemeriksaan <span class="text-red-400">*</span></label>
                        <select id="gradingDetailNama"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                            <option value="">-- Pilih Pemeriksaan --</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Hasil <span class="text-red-400">*</span></label>
                        <select id="gradingDetailHasil"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                            <option value="">-- Pilih Hasil --</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Nilai</label>
                        <input type="number" id="gradingDetailNilai" step="0.01"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>
                    <p id="gradingDetailMsg" class="hidden text-xs text-red-400"></p>
                    <div class="flex gap-2 pt-2">
                        <button id="gradingDetailSave" type="button"
                            class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">Simpan</button>
                        <button id="gradingDetailCancel" type="button"
                            class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">Batal</button>
                    </div>
                </div>
            </div>

        </div>
