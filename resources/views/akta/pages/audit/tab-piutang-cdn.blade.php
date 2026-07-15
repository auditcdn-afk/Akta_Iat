        <div id="tabPanel-piutang-cdn" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan Piutang CDN</h3>
                <button id="pcdnSaveBtn"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                    💾 Simpan
                </button>
            </div>

            {{-- Import Excel --}}
            <div id="pcdnDropzone"
                class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition cursor-pointer hover:border-blue-500">
                <span class="text-4xl">📊</span>
                <p class="text-sm text-slate-300">
                    Drag &amp; drop file <span class="font-semibold text-blue-400">.xls / .xlsx</span> ke sini, atau
                </p>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                    📁 Pilih File Excel
                    <input type="file" id="pcdnFileInput" accept=".xls,.xlsx,.csv" class="hidden">
                </label>
                <p id="pcdnImportMsg" class="hidden text-sm font-medium text-green-400"></p>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatCustomer" class="text-2xl font-bold text-slate-100">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total Debitur</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatSaldo" class="text-lg font-bold text-blue-400">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Saldo Piutang</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatBelumJto" class="text-lg font-bold text-green-400">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Belum Jatuh Tempo</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatTung15" class="text-lg font-bold text-orange-400">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Tunggakan 1–5</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatTung630" class="text-lg font-bold text-orange-500">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Tunggakan 6–30</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatTung3160" class="text-lg font-bold text-red-400">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Tunggakan 31–60</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <p id="pcdnStatTung60" class="text-lg font-bold text-red-500">Rp 0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Tunggakan &gt;60</p>
                </div>
            </div>

            {{-- Tabel Piutang CDN --}}
            <div id="pcdnTableSection" class="hidden overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📋 Daftar Piutang &amp; Tunggakan CDN</span>
                    <span id="pcdnTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Debitur</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1400px] text-xs">
                        <thead class="border-b border-slate-700 bg-slate-800">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-bottom">No</th>
                                <th rowspan="2" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-bottom">No. Kontrak</th>
                                <th rowspan="2" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-bottom">Tanggal</th>
                                <th rowspan="2" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-bottom">Nama Customer</th>
                                <th rowspan="2" class="px-3 py-2 text-right font-semibold uppercase text-slate-400 align-bottom">Saldo Piutang</th>
                                <th rowspan="2" class="px-3 py-2 text-right font-semibold uppercase text-slate-400 align-bottom">Belum JTO</th>
                                <th colspan="4" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 border-b border-slate-700">Tunggakan (Hari)</th>
                                <th colspan="6" class="px-3 py-2 text-center font-semibold uppercase text-slate-400 border-b border-slate-700">Analisa Ketidakaktifan</th>
                                <th rowspan="2" class="px-3 py-2 text-left font-semibold uppercase text-slate-400 align-bottom">Keterangan</th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">1–5</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">6–30</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">31–60</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">&gt;60</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">0 Bln</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">1 Bln</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">2 Bln</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">3 Bln</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">4 Bln</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-500">&gt;5 Bln</th>
                            </tr>
                        </thead>
                        <tbody id="pcdnTableBody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>

        </div>
