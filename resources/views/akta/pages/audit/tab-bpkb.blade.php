        <div id="tabPanel-bpkb" class="audit-tab-panel hidden space-y-5">

            {{-- Import Excel --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-yellow-500 text-xs font-bold text-white">📋</div>
                        <div class="text-sm font-semibold text-slate-200">Import Database Stock BPKB</div>
                    </div>
                    <span id="bpkbDbStatus" class="hidden rounded-full bg-emerald-900/40 px-3 py-1 text-xs font-semibold text-emerald-400 border border-emerald-700">● DATABASE AKTIF</span>
                </div>
                <div id="bpkbDropZone"
                    class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition hover:border-yellow-500 cursor-pointer">
                    <svg class="h-10 w-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-slate-400">Pilih file Excel <strong class="text-slate-200">(.xls / .xlsx)</strong> database onhand BPKB<br>atau drag &amp; drop ke sini</p>
                    <label class="cursor-pointer rounded-xl bg-yellow-500 px-5 py-2 text-sm font-semibold text-white hover:bg-yellow-400">
                        📂 Pilih File Excel
                        <input id="bpkbFileInput" type="file" accept=".xls,.xlsx,.csv" class="hidden">
                    </label>
                    <p id="bpkbFileLabel" class="hidden text-xs text-emerald-400"></p>
                </div>
                <div class="flex items-center gap-3">
                    <button id="bpkbUploadBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        Import Database
                    </button>
                    <button id="bpkbResetBtn" type="button"
                        class="rounded-xl border border-red-800 px-5 py-2 text-sm font-semibold text-red-400 hover:bg-red-900/30">
                        Reset Data
                    </button>
                    <span id="bpkbUploadMsg" class="hidden text-xs"></span>
                </div>
                {{-- Statistik --}}
                <div id="bpkbStats" class="hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3"></div>
                <p id="bpkbCols" class="hidden text-xs text-slate-500"></p>
            </div>

            {{-- Input / Scan --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-blue-400"></span>
                    <span class="text-sm font-semibold text-slate-200">Input No BPKB</span>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400 uppercase tracking-wide">NO BPKB</label>
                    <div class="relative">
                        <input id="bpkbScanInput" type="text" autocomplete="off"
                            placeholder="CONTOH: Q-07856595  ATAU  W1840506-BPKB POLRI 2025"
                            class="w-full rounded-xl border border-slate-600 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
                        <div id="bpkbSuggestions" class="absolute z-10 hidden w-full mt-1 rounded-xl border border-slate-700 bg-slate-800 shadow-xl overflow-hidden"></div>
                    </div>
                    <p id="bpkbScanResult" class="mt-2 hidden text-sm"></p>
                </div>
                <p class="text-xs text-slate-500">Tekan Enter untuk scan. Jika nomor ditemukan, keterangan otomatis berubah menjadi <strong class="text-emerald-400">fisik ada</strong>.</p>
            </div>

            {{-- Hasil --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-200">📋 HASIL PEMERIKSAAN</span>
                    </div>
                    <span id="bpkbScanCount" class="hidden rounded-full bg-blue-900/40 px-3 py-1 text-xs font-semibold text-blue-300 border border-blue-800"></span>
                </div>
                <p id="bpkbResultSummary" class="hidden text-sm text-slate-400"></p>
                <div class="flex gap-2 flex-wrap" id="bpkbResultTabs">
                    <button class="bpkb-result-tab rounded-lg px-4 py-1.5 text-xs font-semibold bg-blue-600 text-white" data-rtab="scan">✅ Sudah Scan <span id="bpkbCountScan">0</span></button>
                    <button class="bpkb-result-tab rounded-lg px-4 py-1.5 text-xs font-semibold text-slate-300 border border-slate-700 hover:bg-slate-800" data-rtab="belum">❌ Belum Scan <span id="bpkbCountBelum">0</span></button>
                    <button class="bpkb-result-tab rounded-lg px-4 py-1.5 text-xs font-semibold text-slate-300 border border-slate-700 hover:bg-slate-800" data-rtab="luar">🔴 Fisik Diluar On Hand <span id="bpkbCountLuar">0</span></button>
                </div>
                <div id="bpkbResultWrap" class="overflow-x-auto"></div>
            </div>

        </div>
