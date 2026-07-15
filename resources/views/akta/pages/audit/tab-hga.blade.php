        <div id="tabPanel-hga" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">🎯 Pemeriksaan HGA (Accessories)</h3>
                <div class="flex gap-2">
                    <button id="hgaClearBtn" type="button"
                        class="flex items-center gap-1.5 rounded-xl border border-red-700/50 bg-red-900/20 px-3 py-2 text-xs font-semibold text-red-400 hover:bg-red-900/40 transition">
                        🗑️ Hapus Semua Data
                    </button>
                    <button id="hgaSaveBtn" type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Import Excel --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Upload Stok HGA --}}
                <div id="hgaDropzone" class="cursor-pointer rounded-2xl border-2 border-dashed border-blue-600/40 bg-blue-900/10 p-5 text-center transition hover:border-blue-400">
                    <div class="mb-1 text-2xl">📊</div>
                    <p class="text-xs font-semibold text-blue-300 mb-1">Stok HGA (Gudang)</p>
                    <p class="text-xs text-slate-400">Drag &amp; drop atau pilih file <span class="text-blue-400 font-semibold">.xls / .xlsx / .csv</span></p>
                    <label class="mt-3 inline-block cursor-pointer rounded-xl bg-yellow-500 px-4 py-1.5 text-xs font-bold text-slate-900 hover:bg-yellow-400 transition">
                        📁 Pilih File Stok HGA
                        <input id="hgaFileInput" type="file" accept=".xls,.xlsx,.csv" class="hidden">
                    </label>
                    <p id="hgaImportMsg" class="hidden mt-2 text-xs text-slate-400"></p>
                </div>

                {{-- Upload PTS / Part Dept --}}
                <div id="hgaPtsDropzone" class="cursor-pointer rounded-2xl border-2 border-dashed border-purple-600/40 bg-purple-900/10 p-5 text-center transition hover:border-purple-400">
                    <div class="mb-1 text-2xl">📋</div>
                    <p class="text-xs font-semibold text-purple-300 mb-1">Saldo PTS / Part Dept</p>
                    <p class="text-xs text-slate-400">Drag &amp; drop atau pilih file <span class="text-purple-400 font-semibold">.xls / .xlsx / .csv</span></p>
                    <label class="mt-3 inline-block cursor-pointer rounded-xl bg-purple-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-purple-500 transition">
                        📁 Pilih File PTS / Part Dept
                        <input id="hgaPtsFileInput" type="file" accept=".xls,.xlsx,.csv" class="hidden">
                    </label>
                    <p id="hgaPtsImportMsg" class="hidden mt-2 text-xs text-slate-400"></p>
                </div>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="hgaStatTotal">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Total HGA</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-red-400" id="hgaStatSelisih">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Selisih</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-green-400" id="hgaStatScan">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Terscan</div>
                </div>
            </div>

            {{-- Form Input Pemeriksaan --}}
            <div class="rounded-2xl border border-blue-700/40 bg-blue-900/10 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-sm font-bold uppercase tracking-wide text-blue-300">🔍 Input Pemeriksaan Fisik</h4>
                    <button id="hgaAddPartBtn" type="button"
                        class="flex items-center gap-1.5 rounded-lg border border-emerald-600/50 bg-emerald-700/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-700/40 transition">
                        + Tambah HGA Manual
                    </button>
                </div>

                <div id="hgaAddPartForm" class="hidden mb-4 rounded-xl border border-emerald-700/40 bg-emerald-900/10 p-4 space-y-3">
                    <p class="text-xs font-semibold text-emerald-300">Tambah No. Part di luar data import</p>
                    <div class="flex gap-2">
                        <input type="text" id="hgaAddPartNo" placeholder="No. HGA (wajib)"
                            class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-emerald-400 focus:outline-none">
                        <input type="text" id="hgaAddPartNama" placeholder="Nama HGA (opsional)"
                            class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div class="flex gap-2">
                        <button id="hgaAddPartSave" type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition">Tambahkan</button>
                        <button id="hgaAddPartCancel" type="button"
                            class="rounded-lg border border-slate-600 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition">Batal</button>
                    </div>
                    <p id="hgaAddPartMsg" class="hidden text-xs"></p>
                </div>

                <div class="space-y-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Tanggal</label>
                        <input type="date" id="hgaFormTgl"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">HGA / No. Part <span class="text-red-400">*</span></label>
                        <input type="text" id="hgaFormPart" list="hgaPartList" autocomplete="off"
                            placeholder="Scan barcode atau pilih No. HGA..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                        <datalist id="hgaPartList"></datalist>
                        <p id="hgaFormPartInfo" class="mt-0.5 text-xs text-slate-400"></p>
                    </div>

                    <div id="hgaFormFields" class="hidden space-y-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Qty (Fisik Scan) <span class="text-red-400">*</span></label>
                            <div class="flex items-center gap-2">
                                <button id="hgaFormQtyDec" type="button" class="rounded-lg bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">−</button>
                                <input type="number" id="hgaFormQty" value="0" min="0"
                                    class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                                <button id="hgaFormQtyInc" type="button" class="rounded-lg bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">+</button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Akhir</label>
                            <input type="text" id="hgaFormAkhir" readonly value="0"
                                class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-300">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Selisih</label>
                            <input type="text" id="hgaFormSelisih" readonly value="0"
                                class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-300">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Keterangan</label>
                            <input type="text" id="hgaFormKet" placeholder="Keterangan..."
                                class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Log Scan</label>
                            <div id="hgaFormLog" class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-400">
                                Fisik Terscan : 0 | Saldo Akhir : -
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <button id="hgaFormSaveBtn" type="button"
                                class="flex-1 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                                ✓ Simpan Pemeriksaan
                            </button>
                            <button id="hgaFormResetBtn" type="button"
                                class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                                Reset
                            </button>
                        </div>
                    </div>
                    <p id="hgaFormMsg" class="text-xs font-medium"></p>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                    <span class="text-sm font-bold text-slate-200">🎯 DATA HGA ACCESSORIES</span>
                    <span id="hgaTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Item</span>
                </div>
                <table class="w-full min-w-[1200px] text-xs">
                    <thead class="border-b border-slate-700 bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-8">No.</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">No. HGA</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama HGA</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-24">Tgl. Periksa</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-20">Saldo Akhir</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-purple-400 w-20">Akhir PTS</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-green-400 w-16">Fisik Scan</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-yellow-400 w-20">Fisik TTP</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16">Akhir</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16">Selisih</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Harga HET</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-28">Jumlah</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-40">Keterangan</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-44">Log Scan</th>
                        </tr>
                    </thead>
                    <tbody id="hgaTableBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>
        </div>
