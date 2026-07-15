        <div id="tabPanel-hgp" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan HGP &amp; AHM Oils</h3>
                <div class="flex items-center gap-2">
                    <button id="hgpClearBtn" type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-red-600/50 bg-red-600/10 px-4 py-2 text-sm font-semibold text-red-300 hover:bg-red-600/20 active:scale-95 transition">
                        🗑️ Hapus Semua Data
                    </button>
                    <button id="hgpSaveBtn" type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Import Excel --}}
            <div id="hgpDropzone"
                class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition cursor-pointer hover:border-blue-500">
                <span class="text-4xl">📊</span>
                <p class="text-sm text-slate-300">
                    Drag &amp; drop file <span class="font-semibold text-blue-400">.xls / .xlsx / .csv</span> data HGP &amp; AHM Oils ke sini, atau
                </p>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                    📁 Pilih File Excel
                    <input type="file" id="hgpFileInput" accept=".xls,.xlsx,.csv" class="hidden">
                </label>
                <p id="hgpImportMsg" class="hidden text-sm font-medium text-green-400"></p>
            </div>

            {{-- Stat --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="hgpStatTotal" class="text-2xl font-bold text-slate-100">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total Sparepart</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="hgpStatSelisih" class="text-2xl font-bold text-red-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Selisih</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="hgpStatScan" class="text-2xl font-bold text-green-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Terscan</p>
                </div>
            </div>

            {{-- Form Pemeriksaan (scan / dropdown No. Part) --}}
            <div class="rounded-2xl border border-blue-700/40 bg-blue-900/10 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-sm font-bold uppercase tracking-wide text-blue-300">🔍 Input Pemeriksaan Fisik</h4>
                    <button id="hgpAddPartBtn" type="button"
                        class="flex items-center gap-1.5 rounded-lg border border-emerald-600/50 bg-emerald-700/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-700/40 transition">
                        + Tambah Part Manual
                    </button>
                </div>

                {{-- Form tambah part manual (tersembunyi) --}}
                <div id="hgpAddPartForm" class="hidden mb-4 rounded-xl border border-emerald-700/40 bg-emerald-900/10 p-4 space-y-3">
                    <p class="text-xs font-semibold text-emerald-300">Tambah No. Part di luar data import</p>
                    <div class="flex gap-2">
                        <input type="text" id="hgpAddPartNo" placeholder="No. Part (wajib)"
                            class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-emerald-400 focus:outline-none">
                        <input type="text" id="hgpAddPartNama" placeholder="Nama Part (opsional)"
                            class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div class="flex gap-2">
                        <button id="hgpAddPartSave" type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition">
                            Tambahkan
                        </button>
                        <button id="hgpAddPartCancel" type="button"
                            class="rounded-lg border border-slate-600 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition">
                            Batal
                        </button>
                    </div>
                    <p id="hgpAddPartMsg" class="hidden text-xs"></p>
                </div>

                <div class="space-y-4">
                    {{-- Tanggal --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Tanggal</label>
                        <input type="date" id="hgpFormTgl"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    {{-- Sparepart / No. Part (scan barcode + dropdown) --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Sparepart / No. Part <span class="text-red-400">*</span></label>
                        <input type="text" id="hgpFormPart" list="hgpPartList" autocomplete="off"
                            placeholder="Scan barcode atau pilih No. Part..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                        <datalist id="hgpPartList"></datalist>
                        <p id="hgpFormPartInfo" class="mt-0.5 text-xs text-slate-400"></p>
                    </div>

                    {{-- Fields berikut hanya muncul setelah No. Part dipilih --}}
                    <div id="hgpFormFields" class="hidden space-y-4">

                        {{-- Qty --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Qty (Fisik Scan) <span class="text-red-400">*</span></label>
                            <div class="flex items-center gap-2">
                                <button id="hgpFormQtyDec" type="button" class="rounded-lg bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">−</button>
                                <input type="number" id="hgpFormQty" value="0" min="0"
                                    class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                                <button id="hgpFormQtyInc" type="button" class="rounded-lg bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">+</button>
                            </div>
                        </div>

                        {{-- Akhir --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Akhir</label>
                            <input type="text" id="hgpFormAkhir" readonly value="0"
                                class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-300">
                        </div>

                        {{-- Selisih --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Selisih</label>
                            <input type="text" id="hgpFormSelisih" readonly value="0"
                                class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-300">
                        </div>

                        {{-- Keterangan --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Keterangan</label>
                            <input type="text" id="hgpFormKet" placeholder="Keterangan..."
                                class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                        </div>

                        {{-- Log Scan --}}
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-slate-300">Log Scan</label>
                            <div id="hgpFormLog" class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2.5 text-sm text-slate-400">
                                Fisik Terscan : 0 | Saldo Akhir : -
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button id="hgpFormSaveBtn" type="button"
                                class="flex-1 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                                ✓ Simpan Pemeriksaan
                            </button>
                            <button id="hgpFormResetBtn" type="button"
                                class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                                Reset
                            </button>
                        </div>

                    </div>
                    <p id="hgpFormMsg" class="text-xs font-medium"></p>
                </div>
            </div>

            {{-- Tabel item HGP --}}
            <div id="hgpTableSection" class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📦 Data HGP &amp; AHM Oils</span>
                    <span id="hgpTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Item</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] text-xs">
                        <thead class="border-b border-slate-700 bg-slate-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-8">No.</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">No. Part</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama Part</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-24">Tgl. Periksa</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-20">Saldo Akhir</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16">Fisik (Qty)</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16 text-amber-400" title="Work Order — menambah Fisik Qty">WO</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16">Akhir</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-16">Selisih</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-24">Harga HET</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-28">Jumlah</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-32">Keterangan</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-44">Log Scan</th>
                            </tr>
                        </thead>
                        <tbody id="hgpTableBody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>

        </div>
