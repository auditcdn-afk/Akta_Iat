        <div id="tabPanel-perlengkapan" class="audit-tab-panel hidden space-y-5">

            {{-- Form Tambah --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-300">📦 Perlengkapan di Luar SMH</h3>
                    <span id="plSmhBadge" class="hidden rounded-full bg-blue-900/50 px-3 py-0.5 text-xs text-blue-300"></span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">No Plan</label>
                        <input id="plNoPlan" type="text" readonly
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama Unit Usaha</label>
                        <input id="plNamaUnit" type="text" readonly
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Pemeriksa</label>
                        <div class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300">
                            <span id="plNamaPemeriksaDisplay">-</span>
                        </div>
                        <input id="plNamaPemeriksaHidden" type="hidden">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tgl Pemeriksaan</label>
                        <input id="plTglPeriksa" type="date"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis Perlengkapan <span class="text-red-400">*</span></label>
                    <select id="plJenisInput"
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">-- Pilih Jenis Perlengkapan --</option>
                    </select>
                    <p id="plJenisSmhInfo" class="mt-1 text-xs text-blue-400 hidden"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Saldo (dari Onhand SMH)</label>
                        <input id="plSaldo" type="number" readonly value="0"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800/50 px-3 py-2 text-sm text-slate-300 outline-none cursor-not-allowed">
                        <p class="mt-0.5 text-xs text-slate-500">Otomatis dari data onhand</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Fisik <span class="text-red-400">*</span></label>
                        <div class="flex items-center gap-2">
                            <button type="button" id="plFisikMinus"
                                class="rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">−</button>
                            <input id="plFisik" type="number" min="0" value="0"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-center text-sm text-slate-100 outline-none focus:border-blue-500">
                            <button type="button" id="plFisikPlus"
                                class="rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-600">+</button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Selisih</label>
                        <input id="plSelisih" type="number" readonly value="0"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-bold outline-none"
                            style="color: #94a3b8">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Penjelasan</label>
                    <textarea id="plPenjelasan" rows="2" placeholder="Keterangan jika ada selisih..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 resize-none"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" id="plResetBtn"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Reset</button>
                    <button type="button" id="plSimpanBtn"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-500">Simpan</button>
                </div>
            </div>

            {{-- Tabel Data --}}
            <div id="plTableWrap" class="hidden rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800 px-5 py-3">
                    <span class="text-sm font-bold text-slate-200">Daftar Pemeriksaan Perlengkapan</span>
                    <span id="plCount" class="text-xs text-slate-400"></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-800/50 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Jenis Perlengkapan</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3 text-right">Fisik</th>
                                <th class="px-4 py-3 text-right">Selisih</th>
                                <th class="px-4 py-3 text-left">Penjelasan</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="plTableBody" class="divide-y divide-slate-800"></tbody>
                    </table>
                </div>
                {{-- Total --}}
                <div class="border-t border-slate-700 bg-slate-800/30 px-5 py-3 grid grid-cols-3 gap-4 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Saldo</div>
                        <div id="plTotalSaldo" class="font-bold text-slate-200">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Fisik</div>
                        <div id="plTotalFisik" class="font-bold text-slate-200">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Selisih</div>
                        <div id="plTotalSelisih" class="font-bold text-slate-200">0</div>
                    </div>
                </div>
            </div>

        </div>
