        <div id="tabPanel-cek-fisik" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan Blangko Cek Fisik &amp; STUJ</h3>
                <button id="cfSaveBtn"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                    💾 Simpan
                </button>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400 text-center">Cek Fisik (CF)</p>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div><p id="cfStatCfAwal" class="text-lg font-bold text-slate-100">0</p><p class="text-slate-500">Saldo Awal</p></div>
                        <div><p id="cfStatCfAkhir" class="text-lg font-bold text-blue-400">0</p><p class="text-slate-500">Saldo Akhir</p></div>
                        <div><p id="cfStatCfSelisih" class="text-lg font-bold text-green-400">0</p><p class="text-slate-500">Selisih</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400 text-center">STUJ</p>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div><p id="cfStatStujAwal" class="text-lg font-bold text-slate-100">0</p><p class="text-slate-500">Saldo Awal</p></div>
                        <div><p id="cfStatStujAkhir" class="text-lg font-bold text-blue-400">0</p><p class="text-slate-500">Saldo Akhir</p></div>
                        <div><p id="cfStatStujSelisih" class="text-lg font-bold text-green-400">0</p><p class="text-slate-500">Selisih</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400 text-center">F. STNK</p>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div><p id="cfStatFstnkAwal" class="text-lg font-bold text-slate-100">0</p><p class="text-slate-500">Saldo Awal</p></div>
                        <div><p id="cfStatFstnkAkhir" class="text-lg font-bold text-blue-400">0</p><p class="text-slate-500">Saldo Akhir</p></div>
                        <div><p id="cfStatFstnkSelisih" class="text-lg font-bold text-green-400">0</p><p class="text-slate-500">Selisih</p></div>
                    </div>
                </div>
            </div>

            {{-- Saldo Awal --}}
            <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="border-b border-slate-700 bg-slate-800/60 px-5 py-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📅 Saldo Awal</span>
                </div>
                <div class="px-5 py-4 grid grid-cols-4 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-slate-400">Tanggal</label>
                        <input type="date" id="cfSaldoAwalTgl"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-slate-400">Cek Fisik</label>
                        <input type="number" id="cfSaldoAwalCf" value="0" min="0"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-slate-400">STUJ</label>
                        <input type="number" id="cfSaldoAwalStuj" value="0" min="0"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-slate-400">F. STNK</label>
                        <input type="number" id="cfSaldoAwalFstnk" value="0" min="0"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc">
                    </div>
                </div>
            </div>

            {{-- Penerimaan --}}
            <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="border-b border-slate-700 bg-slate-800/60 px-5 py-2 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📥 Penerimaan</span>
                    <button id="cfAddPenerimaan" type="button"
                        class="rounded-lg border border-dashed border-slate-500 px-3 py-1 text-xs text-slate-400 hover:border-blue-400 hover:text-blue-400">
                        + Tambah Baris
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-800 text-xs text-slate-400">
                        <th class="px-4 py-2 text-left w-36">Tanggal</th>
                        <th class="px-4 py-2 text-left">No. Dokumen</th>
                        <th class="px-4 py-2 text-right w-24">Cek Fisik</th>
                        <th class="px-4 py-2 text-right w-24">STUJ</th>
                        <th class="px-4 py-2 text-right w-24">F. STNK</th>
                        <th class="px-4 py-2 w-8"></th>
                    </tr></thead>
                    <tbody id="cfPenerimaanBody"></tbody>
                </table>
            </div>

            {{-- Pengeluaran --}}
            <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="border-b border-slate-700 bg-slate-800/60 px-5 py-2 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📤 Pengeluaran</span>
                    <button id="cfAddPengeluaran" type="button"
                        class="rounded-lg border border-dashed border-slate-500 px-3 py-1 text-xs text-slate-400 hover:border-blue-400 hover:text-blue-400">
                        + Tambah Baris
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-800 text-xs text-slate-400">
                        <th class="px-4 py-2 text-left">No. Dokumen</th>
                        <th class="px-4 py-2 text-right w-24">Cek Fisik</th>
                        <th class="px-4 py-2 text-right w-24">STUJ</th>
                        <th class="px-4 py-2 text-right w-24">F. STNK</th>
                        <th class="px-4 py-2 w-8"></th>
                    </tr></thead>
                    <tbody id="cfPengeluaranBody"></tbody>
                </table>
            </div>

            {{-- Saldo Akhir / Fisik / Selisih --}}
            <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="border-b border-slate-700 bg-slate-800/60 px-5 py-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📊 Saldo Akhir, Fisik &amp; Selisih</span>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-slate-800 text-xs text-slate-400">
                        <th class="px-4 py-2 text-left">Keterangan</th>
                        <th class="px-4 py-2 text-right w-32">Cek Fisik</th>
                        <th class="px-4 py-2 text-right w-32">STUJ</th>
                        <th class="px-4 py-2 text-right w-32">F. STNK</th>
                    </tr></thead>
                    <tbody id="cfRingkasanBody"></tbody>
                </table>
            </div>

        </div>
