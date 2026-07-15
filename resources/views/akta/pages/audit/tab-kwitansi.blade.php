        <div id="tabPanel-kwitansi" class="audit-tab-panel hidden space-y-5">

            {{-- Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan Kwitansi Gantung</h3>
                <div class="flex items-center gap-3">
                    <span id="kwSaveMsg" class="hidden text-xs"></span>
                    <button id="kwSaveBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Tanggal Audit + Import Excel --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                {{-- Tgl Audit --}}
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Tanggal Audit (untuk hitung DIFF)</p>
                    <input id="kwTglAudit" type="date"
                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                </div>

                {{-- Import Excel --}}
                <div id="kwDropzone"
                    class="rounded-xl border-2 border-dashed border-slate-600 bg-slate-900 p-5 text-center cursor-pointer hover:border-blue-500 transition-colors">
                    <div class="text-3xl mb-2">📊</div>
                    <p class="text-sm text-slate-400">Drag &amp; drop file <span class="text-blue-400 font-semibold">.xls / .xlsx</span> ke sini, atau</p>
                    <label class="mt-3 inline-block cursor-pointer rounded-xl bg-slate-700 px-5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-600">
                        📂 Pilih File Excel
                        <input id="kwFileInput" type="file" accept=".xls,.xlsx" class="hidden">
                    </label>
                    <p id="kwImportMsg" class="mt-2 text-xs text-emerald-400 hidden"></p>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div id="kwStatTransaksi" class="text-2xl font-bold text-slate-100">0</div>
                    <div class="text-xs text-slate-400 mt-1 uppercase tracking-wide">Total Transaksi</div>
                </div>
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div id="kwStatCustomer" class="text-2xl font-bold text-slate-100">0</div>
                    <div class="text-xs text-slate-400 mt-1 uppercase tracking-wide">Customer</div>
                </div>
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div id="kwStatLeasing" class="text-2xl font-bold text-slate-100">0</div>
                    <div class="text-xs text-slate-400 mt-1 uppercase tracking-wide">Leasing</div>
                </div>
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div id="kwStatNilai" class="text-2xl font-bold text-red-400">0</div>
                    <div class="text-xs text-slate-400 mt-1 uppercase tracking-wide">Total Nilai</div>
                </div>
            </div>

            {{-- Rata-rata Hari --}}
            <div id="kwAvgSection" class="hidden rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">
                <div class="px-4 py-3 bg-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-300">Rata-rata Hari Kwitansi Gantung</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span id="kwAvgAll" class="text-3xl font-bold text-slate-100">0.0</span>
                            <span class="text-sm text-slate-400">hari</span>
                        </div>
                        <p id="kwAvgSubtitle" class="text-xs text-slate-500 mt-0.5"></p>
                    </div>
                    <div id="kwAvgPerLeasing" class="flex gap-4"></div>
                </div>
            </div>

            {{-- Tabel Kwitansi --}}
            <div id="kwTableSection" class="hidden rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-800 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🧾 Daftar Kwitansi Gantung</span>
                    <span id="kwTableCount" class="text-xs text-slate-400"></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-950/60">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-8">No.</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">No. Kwitansi</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Tgl. Kwitansi</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama Customer</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">No. AR</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">No. Faktur</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400">Nilai Kwitansi (Rp)</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-14">Diff</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-40">Keterangan</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-12">Fisik</th>
                            </tr>
                        </thead>
                        <tbody id="kwTableBody" class="divide-y divide-slate-800"></tbody>
                    </table>
                </div>
            </div>

        </div>
