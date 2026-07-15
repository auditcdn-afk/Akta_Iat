        <div id="tabPanel-ttp-gantung" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan TTP Gantung</h3>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-400">Tgl. Audit</label>
                        <input type="date" id="ttpTglAudit"
                            class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                    </div>
                    <button id="ttpSaveBtn"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Import HTML --}}
            <div id="ttpDropzone"
                class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition cursor-pointer hover:border-blue-500">
                <span class="text-4xl">📄</span>
                <p class="text-sm text-slate-300">
                    Drag &amp; drop file <span class="font-semibold text-blue-400">.html / .htm</span> laporan TTP Gantung ke sini, atau
                </p>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                    📁 Pilih File HTML
                    <input type="file" id="ttpFileInput" accept=".html,.htm" class="hidden">
                </label>
                <p id="ttpImportMsg" class="hidden text-sm font-medium text-green-400"></p>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="ttpStatTotal" class="text-2xl font-bold text-slate-100">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total Data</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="ttpStatBelum" class="text-lg font-bold text-orange-400">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total Tagihan Belum Cair</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="ttpStatDiff" class="text-lg font-bold text-red-400">0 hari</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Diff Terlama</p>
                </div>
            </div>

            {{-- Tabel TTP --}}
            <div id="ttpTableSection" class="hidden overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">🧾 Daftar Tagihan TTP Gantung</span>
                    <span id="ttpTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Data</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1300px] text-xs">
                        <thead class="border-b border-slate-700 bg-slate-800">
                            <tr>
                                <th rowspan="3" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-middle">No.</th>
                                <th colspan="6" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 border-b border-slate-700">Penagihan</th>
                                <th colspan="2" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 border-b border-slate-700">Pencairan</th>
                                <th rowspan="3" class="px-3 py-2 text-right font-semibold uppercase text-slate-400 align-middle">Tagihan Belum Cair</th>
                                <th rowspan="3" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-middle">Keterangan</th>
                                <th rowspan="3" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 align-middle">Diff<br>(Hari)</th>
                                <th rowspan="3" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 align-middle">Fisik</th>
                            </tr>
                            <tr>
                                <th colspan="2" class="px-3 py-2 text-center font-semibold uppercase text-slate-500 border-b border-slate-700">TTP</th>
                                <th colspan="4" class="px-3 py-2 text-center font-semibold uppercase text-slate-500 border-b border-slate-700">Faktur</th>
                                <th rowspan="2" class="px-3 py-2 text-center font-semibold uppercase text-slate-500 align-middle">Tanggal</th>
                                <th rowspan="2" class="px-3 py-2 text-right font-semibold uppercase text-slate-500 align-middle">Nilai</th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-500">No.</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-500">Tgl.</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-500">No.</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-500">Nama</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">Nilai</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">Sudah Cair</th>
                            </tr>
                        </thead>
                        <tbody id="ttpTableBody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>

        </div>
