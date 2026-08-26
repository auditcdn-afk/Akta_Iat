        <div id="tabPanel-ttp-csc" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan TTP CSC</h3>
                <button id="tcSaveBtn"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                    💾 Simpan
                </button>
            </div>

            <p class="text-xs text-slate-400">
                Import laporan TTP Panjar (.xls) — hanya bagian <span class="font-semibold text-slate-200">"II. TTP
                Sesuai Periode Filter"</span> yang diambil. Isi <span class="font-semibold text-slate-200">Tanggal
                Portal</span> manual per baris (dari hasil cek di portal); <span class="font-semibold text-slate-200">Selisih
                Tgl</span> dan <span class="font-semibold text-slate-200">Keterangan</span> terhitung otomatis
                (0 hari → "Data Sesuai", selain itu → "Selisih") — Keterangan tetap bisa diedit manual.
            </p>

            {{-- Import Excel --}}
            <div id="tcDropzone"
                class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition cursor-pointer hover:border-blue-500">
                <span class="text-4xl">📊</span>
                <p class="text-sm text-slate-300">
                    Drag &amp; drop file <span class="font-semibold text-blue-400">.xls / .xlsx</span> ke sini, atau
                </p>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                    📁 Pilih File Excel
                    <input type="file" id="tcFileInput" accept=".xls,.xlsx,.csv" class="hidden">
                </label>
                <p id="tcImportMsg" class="hidden text-sm font-medium text-green-400"></p>
            </div>

            {{-- Stat Cards --}}
            <div id="tcStatSection" class="hidden grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="tcStatTotal" class="text-2xl font-bold text-slate-100">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total TTP</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="tcStatSesuai" class="text-2xl font-bold text-green-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Data Sesuai</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="tcStatSelisih" class="text-2xl font-bold text-red-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Selisih / Belum Dicek</p>
                </div>
            </div>

            {{-- Tabel Hasil --}}
            <div id="tcTableSection" class="hidden overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📋 Pemeriksaan TTP CSC</span>
                    <span id="tcTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 baris</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-xs">
                        <thead class="border-b border-slate-700 bg-slate-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">No</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">TTP</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Tanggal</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400">Nilai</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Tanggal Portal</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400">Selisih Tgl</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="tcTableBody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>

        </div>
