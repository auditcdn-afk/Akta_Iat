        <div id="tabPanel-kas" class="audit-tab-panel space-y-5">
            <input type="hidden" id="kasId">
            <input type="hidden" id="kasPlanAuditId">

            {{-- ── KAS BESAR ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 text-slate-100 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">💰 Kas Besar</div>
                <div class="space-y-4 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Tanggal H-1 Pemeriksaan)</label>
                            <input id="kbSaldoAwalTgl" type="date"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Rp)</label>
                            <input id="kbSaldoAwal" type="text" inputmode="numeric" value=""
                                class="kb-calc w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        </div>
                    </div>

                    {{-- Penerimaan --}}
                    <div>
                        <div class="mb-2 text-sm font-bold text-emerald-600">▲ Penerimaan</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kbPenerimaanBody"></tbody>
                        </table>
                        <button type="button" data-add="kbPenerimaan" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-500/10">+ Tambah Penerimaan</button>
                    </div>

                    {{-- Pengeluaran --}}
                    <div>
                        <div class="mb-2 text-sm font-bold text-red-500">▼ Pengeluaran</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kbPengeluaranBody"></tbody>
                        </table>
                        <button type="button" data-add="kbPengeluaran" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-500/10">+ Tambah Pengeluaran</button>
                    </div>

                    {{-- Ringkasan Kas Besar --}}
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm">
                        <div class="flex justify-between py-1 text-slate-300"><span>Saldo Awal</span><span id="kbSumSaldoAwal" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-300"><span>Total Penerimaan</span><span id="kbSumPenerimaan" class="font-semibold text-emerald-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-300"><span>Total Pengeluaran</span><span id="kbSumPengeluaran" class="font-semibold text-red-500">Rp 0</span></div>
                        <div class="mt-1 flex justify-between border-t border-slate-700 py-2 font-bold text-slate-100"><span>Saldo Buku (Sistem)</span><span id="kbSaldoBuku">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-300"><span>Saldo Fisik (Uang Fisik)</span><span id="kbSaldoFisik" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 font-bold"><span class="text-red-500">Selisih</span><span id="kbSelisih" class="text-red-500">Rp 0</span></div>
                        <div class="mt-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan</label>
                            <input id="kbKeterangan" type="text" placeholder="contoh: Selisih lebih pembulatan"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── KAS KECIL ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 text-slate-100 shadow">
                <div class="bg-[#2d8a4e] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🪙 Kas Kecil</div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cadangan Kas Kecil (Rp)</label>
                        <input id="kkCadangan" type="text" inputmode="numeric" value=""
                            class="kk-calc w-full max-w-sm rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-bold text-amber-600">🧾 Rincian Bon Gantung Kas Kecil</div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left w-40">Tanggal</th>
                                    <th class="px-3 py-2 text-left">Keterangan</th>
                                    <th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="kkBonBody"></tbody>
                        </table>
                        <button type="button" data-add="kkBon" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-500/10">+ Tambah Bon Gantung</button>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm">
                        <div class="flex justify-between py-1 text-slate-300"><span>Cadangan Kas Kecil</span><span id="kkSumCadangan" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-300"><span>Total Bon Gantung</span><span id="kkSumBon" class="font-semibold text-amber-600">Rp 0</span></div>
                        <div class="mt-1 flex justify-between border-t border-slate-700 py-2 font-bold text-slate-100"><span>Saldo Buku (Sistem)</span><span id="kkSaldoBuku">Rp 0</span></div>
                        <div class="flex justify-between py-1 text-slate-300"><span>Saldo Fisik (Uang Fisik)</span><span id="kkSaldoFisik" class="font-semibold text-blue-600">Rp 0</span></div>
                        <div class="flex justify-between py-1 font-bold"><span class="text-red-500">Selisih</span><span id="kkSelisih" class="text-red-500">Rp 0</span></div>
                        <div class="mt-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan</label>
                            <input id="kkKeterangan" type="text" placeholder="contoh: Selisih lebih pembulatan"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── RINCIAN PECAHAN NOMINAL UANG KAS ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 text-slate-100 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">💵 Rincian Pecahan Nominal Uang Kas</div>
                <div class="overflow-x-auto p-5">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-right">Pecahan (Rp)</th>
                                <th class="px-3 py-2 text-center">Jumlah Lembar/Keping<br>Kas Besar</th>
                                <th class="px-3 py-2 text-right">Total<br>Kas Besar</th>
                                <th class="px-3 py-2 text-center">Jumlah Lembar/Keping<br>Kas Kecil</th>
                                <th class="px-3 py-2 text-right">Total<br>Kas Kecil</th>
                            </tr>
                        </thead>
                        <tbody id="pecahanBody"></tbody>
                        <tfoot class="bg-slate-950/60 font-bold text-slate-100">
                            <tr>
                                <td class="px-3 py-2 text-right">TOTAL</td>
                                <td class="px-3 py-2 text-center text-amber-600" id="pecahanTotalLembarBesar">0</td>
                                <td class="px-3 py-2 text-right" id="pecahanTotalBesar">Rp 0</td>
                                <td class="px-3 py-2 text-center text-amber-600" id="pecahanTotalLembarKecil">0</td>
                                <td class="px-3 py-2 text-right" id="pecahanTotalKecil">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- ── REGISTER BLANKO ── --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 text-slate-100 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">📋 Register Blanko yang Belum Digunakan</div>
                <div class="space-y-5 p-5">
                    <div>
                        <div class="mb-2 border-b border-slate-800 pb-1 text-sm font-bold text-slate-300">H1</div>
                        <table class="w-full text-sm">
                            <tbody id="blankoH1Body"></tbody>
                        </table>
                        <button type="button" data-add="blankoH1" class="add-row-btn mt-2 rounded-lg border border-dashed border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800">+ Tambah Register Blanko H1</button>
                    </div>
                    <div>
                        <div class="mb-2 border-b border-slate-800 pb-1 text-sm font-bold text-slate-300">H2</div>
                        <table class="w-full text-sm">
                            <tbody id="blankoH2Body"></tbody>
                        </table>
                        <button type="button" data-add="blankoH2" class="add-row-btn mt-2 rounded-lg border border-dashed border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800">+ Tambah Register Blanko H2</button>
                    </div>
                </div>
            </div>

            {{-- Aksi simpan --}}
            <div class="flex justify-end gap-3">
                <button type="button" id="saveKasFormBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500/100">
                    Simpan Pemeriksaan Kas
                </button>
            </div>
        </div>
