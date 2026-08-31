        <div id="tabPanel-mutasi-pembelian" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan Mutasi Pembelian</h3>
                <button id="mpSaveBtn"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-500 active:scale-95 transition">
                    💾 Simpan
                </button>
            </div>

            <p class="text-xs text-slate-400">
                Bandingkan laporan pembelian <span class="font-semibold text-slate-200">Gudang</span> (patokan — tiap
                barisnya dicek) terhadap catatan <span class="font-semibold text-slate-200">Unit Usaha</span> (dipakai
                untuk memverifikasi: apakah Kode Part + Qty + Nomor Faktur sudah tercatat diterima). Kolom Keterangan
                hasilnya bisa diubah manual per baris.
            </p>

            {{-- Import 2 file --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div id="mpDropzoneGudang"
                    class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 text-center transition cursor-pointer hover:border-blue-500">
                    <span class="text-3xl">🏭</span>
                    <p class="text-sm font-semibold text-slate-200">File Gudang (patokan)</p>
                    <p class="text-xs text-slate-400">.xls / .xlsx / .csv</p>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                        📁 Pilih File
                        <input type="file" id="mpFileGudang" accept=".xls,.xlsx,.csv" class="hidden">
                    </label>
                    <p id="mpFileGudangName" class="hidden text-xs font-medium text-green-400"></p>
                </div>
                <div id="mpDropzoneUu"
                    class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 text-center transition cursor-pointer hover:border-blue-500">
                    <span class="text-3xl">🏢</span>
                    <p class="text-sm font-semibold text-slate-200">File Unit Usaha (pembanding)</p>
                    <p class="text-xs text-slate-400">.xls / .xlsx / .csv</p>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-yellow-400 transition">
                        📁 Pilih File
                        <input type="file" id="mpFileUu" accept=".xls,.xlsx,.csv" class="hidden">
                    </label>
                    <p id="mpFileUuName" class="hidden text-xs font-medium text-green-400"></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="mpCompareBtn"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500 active:scale-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    🔍 Bandingkan
                </button>
                <p id="mpCompareMsg" class="hidden text-sm font-medium"></p>
            </div>

            {{-- Stat Cards — sekaligus tombol filter tabel di bawahnya. Auditor
                 paling sering hanya perlu menindaklanjuti yang "Belum Terima",
                 jadi angkanya langsung bisa diklik untuk menyaring. --}}
            <div id="mpStatSection" class="hidden grid grid-cols-3 gap-3">
                <button type="button" data-mp-filter="semua"
                    class="mp-filter-btn rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center transition hover:border-slate-500">
                    <p id="mpStatTotal" class="text-2xl font-bold text-slate-100">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Total Baris Gudang</p>
                </button>
                <button type="button" data-mp-filter="sudah"
                    class="mp-filter-btn rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center transition hover:border-green-600">
                    <p id="mpStatMatch" class="text-2xl font-bold text-green-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Sudah Diterima</p>
                </button>
                <button type="button" data-mp-filter="belum"
                    class="mp-filter-btn rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center transition hover:border-red-600">
                    <p id="mpStatUnmatch" class="text-2xl font-bold text-red-400">0</p>
                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">Belum Terima</p>
                </button>
            </div>

            {{-- Tabel Hasil --}}
            <div id="mpTableSection" class="hidden overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-200">📋 Hasil Perbandingan Mutasi Pembelian</span>
                    <div class="flex items-center gap-2">
                        <span id="mpFilterLabel" class="hidden rounded-full border border-red-700/60 bg-red-950/40 px-3 py-1 text-xs font-semibold text-red-300"></span>
                        <span id="mpTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 baris</span>
                    </div>
                </div>
                {{-- Pencarian isi tabel — jalan pintas mencari 1 No. Part / No. Faktur
                     tanpa menggulir ribuan baris. Bekerja bersama filter di atas. --}}
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 bg-slate-900/60 px-5 py-2">
                    <input type="search" id="mpTableSearch" placeholder="🔎 Cari No. Part / Nama Part / No. Faktur..."
                        class="w-full max-w-sm rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none">
                    <button type="button" id="mpFilterReset"
                        class="hidden rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition">
                        ✕ Tampilkan semua
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-xs">
                        <thead class="border-b border-slate-700 bg-slate-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">No</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Kode Part</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama Part</th>
                                <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400">Qty</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nomor Faktur</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Tanggal Faktur</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Lokasi</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Kode</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Unit Usaha</th>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Keterangan</th>
                                <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400"></th>
                            </tr>
                        </thead>
                        <tbody id="mpTableBody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>

        </div>
