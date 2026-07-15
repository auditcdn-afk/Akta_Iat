        <div id="tabPanel-smh" class="audit-tab-panel hidden space-y-5">

            {{-- Upload Onhand --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="flex items-center justify-between bg-[#1e3a5f] px-5 py-3 text-white">
                    <span class="text-sm font-bold uppercase tracking-wide">📋 Upload File Onhand SMH</span>
                    <span id="smhTglOnhand" class="text-xs text-blue-200"></span>
                </div>
                <div class="flex flex-wrap items-end gap-4 p-5">
                    <div class="flex-1 min-w-60">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">File Onhand (.xls / .xlsx)</label>
                        <input id="smhFileInput" type="file" accept=".xls,.xlsx"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
                    </div>
                    <button type="button" id="smhUploadBtn"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 whitespace-nowrap">
                        Upload &amp; Proses
                    </button>
                    <button type="button" id="smhSyncBtn"
                        class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 whitespace-nowrap hidden">
                        🔗 Sinkron Perlengkapan
                    </button>
                </div>
            </div>

            {{-- Summary --}}
            <div id="smhSummary" class="hidden grid gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="smhTotalUnit">0</div>
                    <div class="text-xs text-slate-400 mt-1">Total Unit</div>
                </div>
                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-400" id="smhTotalAda">0</div>
                    <div class="text-xs text-slate-400 mt-1">Ditemukan</div>
                </div>
                <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-red-400" id="smhTotalTidakAda">0</div>
                    <div class="text-xs text-slate-400 mt-1">Tidak Ditemukan</div>
                </div>
                <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-center">
                    <div class="text-2xl font-bold text-amber-400" id="smhTotalBelum">0</div>
                    <div class="text-xs text-slate-400 mt-1">Belum Diperiksa</div>
                </div>
            </div>

            {{-- Scan / Cari unit --}}
            <div id="smhScanBox" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#2d8a4e] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🔍 Pemeriksaan Fisik — Scan / Cari Unit</div>
                <div class="p-5 space-y-3">
                    <div class="relative flex gap-3">
                        <div class="relative flex-1">
                            <input id="smhScanInput" type="text" autocomplete="off"
                                placeholder="Scan atau ketik No. Mesin / No. Rangka..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500">
                            {{-- Suggestions list --}}
                            <ul id="smhSuggestions"
                                class="absolute left-0 right-0 top-full z-50 hidden max-h-60 overflow-y-auto rounded-b-lg border border-t-0 border-slate-300 bg-white shadow-lg">
                            </ul>
                        </div>
                        <button type="button" id="smhScanBtn"
                            class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 whitespace-nowrap">
                            Cek
                        </button>
                        <button type="button" id="smhManualBtn"
                            class="rounded-xl border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 whitespace-nowrap">
                            + Tambah Manual
                        </button>
                    </div>
                    <p class="text-xs text-slate-400">Ketik minimal 2 karakter untuk melihat saran unit, atau scan barcode langsung.</p>
                    <div id="smhScanResult" class="hidden rounded-xl border p-4 text-sm"></div>
                </div>
            </div>

            {{-- Tabel unit --}}
            <div id="smhTableWrap" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="flex items-center justify-between bg-[#1e3a5f] px-5 py-3 text-white">
                    <span class="text-sm font-bold uppercase tracking-wide">Daftar Unit On Hand</span>
                    <div class="flex gap-2 items-center text-xs">
                        <select id="smhFilterStatus"
                            class="rounded-lg border border-blue-300 bg-[#1e3a5f] px-2 py-1 text-white text-xs outline-none">
                            <option value="">Semua Status</option>
                            <option value="ada">Ditemukan</option>
                            <option value="tidak_ada">Tidak Ditemukan</option>
                            <option value="belum">Belum Diperiksa</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">No</th>
                                <th class="px-3 py-2 text-left">No. Mesin</th>
                                <th class="px-3 py-2 text-left">No. Rangka</th>
                                <th class="px-3 py-2 text-left">No. SPB</th>
                                <th class="px-3 py-2 text-left">Tgl SPB</th>
                                <th class="px-3 py-2 text-center">Umur</th>
                                <th class="px-3 py-2 text-left">Model</th>
                                <th class="px-3 py-2 text-left">Warna</th>
                                <th class="px-3 py-2 text-left">Gudang</th>
                                <th class="px-3 py-2 text-center w-40">Status Fisik</th>
                                <th class="px-3 py-2 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="smhTableBody"></tbody>
                    </table>
                </div>
            </div>

            {{-- Hasil sync perlengkapan --}}
            <div id="smhSyncResult" class="hidden overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🔗 Hasil Sinkronisasi Perlengkapan</div>
                <div id="smhSyncBody" class="p-5 space-y-2 text-sm"></div>
            </div>
        </div>
