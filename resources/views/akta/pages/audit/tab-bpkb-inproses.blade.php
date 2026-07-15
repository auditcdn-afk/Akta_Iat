        <div id="tabPanel-bpkb-inproses" class="audit-tab-panel hidden space-y-5">

            {{-- Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-100">Pemeriksaan BPKB Inproses</h3>
                <div class="flex items-center gap-3">
                    <button id="bpkiAddBlockBtn" type="button"
                        class="rounded-xl border border-blue-500 px-4 py-2 text-xs font-semibold text-blue-400 hover:bg-blue-500/10">
                        + Tambah Kolom Inproses
                    </button>
                    <span id="bpkiSaveMsg" class="hidden text-xs"></span>
                    <button id="bpkiSaveBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Horizontal scroll: kiri (fixed) + kanan (dynamic blocks) --}}
            <div class="overflow-x-auto">
                <div class="flex gap-5 min-w-max">

                    {{-- Kiri: Laporan Posisi BPKB (fixed width) --}}
                    <div class="w-80 flex-shrink-0 space-y-4">
                        <div class="rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">
                            <div class="px-4 py-2.5 bg-slate-800">
                                <span class="text-xs font-bold text-amber-300 uppercase tracking-wide">📋 Laporan Posisi BPKB</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Tanggal Awal</label>
                                    <input id="bpkiTglAwal" type="date" class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Saldo Fisik BPKB (Unit)</label>
                                    <input id="bpkiSaldoAwalFisik" type="number" min="0" value="0"
                                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
                                </div>
                            </div>
                        </div>

                        {{-- Penerimaan Fisik --}}
                        <div class="rounded-xl border border-emerald-800 bg-slate-900 overflow-hidden">
                            <div class="px-4 py-2.5 bg-emerald-900/60">
                                <span class="text-xs font-bold text-emerald-300 uppercase">✅ Penerimaan Fisik BPKB</span>
                            </div>
                            <div class="p-4">
                                <div id="bpkiPenerimaanFisikRows" class="space-y-1"></div>
                                <button type="button" data-bpki-add="penerimaanFisik"
                                    class="mt-2 rounded-lg border border-dashed border-slate-600 px-4 py-1.5 text-xs font-semibold text-slate-400 hover:border-emerald-500 hover:text-emerald-400">
                                    + Tambah Baris
                                </button>
                            </div>
                        </div>

                        {{-- Pengeluaran --}}
                        <div class="rounded-xl border border-red-800 bg-slate-900 overflow-hidden">
                            <div class="px-4 py-2.5 bg-red-900/60">
                                <span class="text-xs font-bold text-red-300 uppercase">▼ Pengeluaran BPKB</span>
                            </div>
                            <div class="p-4">
                                <div id="bpkiPengeluaranBpkbRows" class="space-y-1"></div>
                                <button type="button" data-bpki-add="pengeluaranBpkb"
                                    class="mt-2 rounded-lg border border-dashed border-slate-600 px-4 py-1.5 text-xs font-semibold text-slate-400 hover:border-red-500 hover:text-red-400">
                                    + Tambah Baris
                                </button>
                            </div>
                        </div>

                        {{-- Rekap Fisik --}}
                        <div class="rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">
                            <div class="px-4 py-2.5 bg-slate-800">
                                <span class="text-xs font-bold text-slate-200 uppercase">📊 Rekap Fisik BPKB</span>
                            </div>
                            <div class="p-4 space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-slate-400">Saldo Awal</span><span id="bpkiRFisikSaldoAwal" class="text-slate-200">0</span></div>
                                <div class="flex justify-between"><span class="text-emerald-400">+ Penerimaan</span><span id="bpkiRFisikPenerimaan" class="text-emerald-400">0</span></div>
                                <div class="flex justify-between"><span class="text-red-400">− Pengeluaran</span><span id="bpkiRFisikPengeluaran" class="text-red-400">0</span></div>
                                <div class="flex justify-between border-t border-slate-700 pt-2 font-bold"><span class="text-slate-200">Saldo Buku</span><span id="bpkiRFisikBuku" class="text-slate-100">0</span></div>
                                <div class="flex justify-between font-bold text-base"><span class="text-amber-300">Selisih</span><span id="bpkiRFisikSelisih" class="text-emerald-400">Nihil</span></div>
                                <div class="mt-3">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Fisik BPKB (Hitung)</label>
                                    <input id="bpkiFisikBpkbHitung" type="number" min="0" placeholder="0"
                                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
                                </div>
                                <div class="mt-2">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Keterangan</label>
                                    <textarea id="bpkiKeteranganSelisih" rows="2" placeholder="Keterangan selisih..."
                                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Kanan: Dynamic Inproses Blocks --}}
                    <div id="bpkiInprosesBlocks" class="flex gap-4 items-start"></div>

                </div>
            </div>

            {{-- Keterangan Selisih & Rincian (dynamic, per block) --}}
            <div id="bpkiKetSelisihSection" class="space-y-4"></div>

            {{-- On Hand BPKB vs Fisik --}}
            <div class="rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-800">
                    <span class="text-xs font-bold text-slate-200 uppercase">📊 On Hand BPKB vs Fisik BPKB</span>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">On Hand BPKB (dari stock di BO)</label>
                        <input id="bpkiOnhandBpkb" type="number" min="0" value="0"
                            class="w-48 rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">Fisik BPKB</span><span id="bpkiOhFisik" class="text-slate-200">0</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">On Hand BPKB</span><span id="bpkiOhOnhand" class="text-slate-200">0</span></div>
                        <div class="flex justify-between font-bold"><span class="text-amber-300">Selisih On Hand vs Fisik</span><span id="bpkiOhSelisih" class="text-emerald-400">Nihil</span></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Keterangan Selisih On Hand</label>
                        <textarea id="bpkiKeteranganSelisihOnhand" rows="3"
                            placeholder="Contoh: Selisih sebanyak 73 merupakan BPKB yang belum di input ke sistem via SP..."
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

        </div>
