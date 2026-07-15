        <div id="tabPanel-plafon" class="audit-tab-panel hidden space-y-5">

            {{-- Unit Usaha Terpilih --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-600 text-xs font-bold text-white">●</div>
                    <span class="text-sm font-semibold text-slate-200">Unit Usaha Terpilih</span>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Kode Unit Usaha</div>
                        <div id="pfKodeUnit" class="text-sm font-bold text-slate-100">—</div>
                    </div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Plafon Cover</div>
                        <div id="pfPlafonCover" class="text-sm font-bold text-blue-300">—</div>
                    </div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Daerah</div>
                        <div id="pfDaerah" class="text-sm font-bold text-slate-100">—</div>
                    </div>
                </div>
            </div>

            {{-- Hasil Analisa --}}
            <div id="pfAnalisaWrap" class="hidden rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">3</div>
                    <div>
                        <div class="text-sm font-semibold text-slate-200">Hasil Analisa</div>
                        <div id="pfAnalisaSubtitle" class="text-xs text-slate-400"></div>
                    </div>
                </div>

                {{-- Kartu Statistik --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl bg-slate-800 px-4 py-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Unit</div>
                        <div id="pfStatTotal" class="text-2xl font-bold text-slate-100">0</div>
                        <div class="text-xs text-slate-500">unit di onhand</div>
                    </div>
                    <div class="rounded-xl bg-slate-800 px-4 py-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Ditemukan</div>
                        <div id="pfStatDitemukan" class="text-2xl font-bold text-emerald-400">0</div>
                        <div class="text-xs text-slate-500">ada di database</div>
                    </div>
                    <div class="rounded-xl bg-slate-800 px-4 py-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Tidak Ditemukan</div>
                        <div id="pfStatTidak" class="text-2xl font-bold text-orange-400">0</div>
                        <div class="text-xs text-slate-500">kode tidak ada</div>
                    </div>
                    <div class="rounded-xl bg-slate-800 px-4 py-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Plafon Cover</div>
                        <div id="pfStatPlafon" class="text-sm font-bold text-blue-300">—</div>
                        <div class="text-xs text-slate-500">batas nilai SMH</div>
                    </div>
                    <div class="rounded-xl bg-slate-800 px-4 py-3 text-center">
                        <div class="text-xs text-slate-400 mb-1">Total Nilai SMH</div>
                        <div id="pfStatNilai" class="text-sm font-bold text-blue-400">Rp 0</div>
                        <div class="text-xs text-slate-500">yang ditemukan</div>
                    </div>
                </div>

                {{-- Progress bar plafon --}}
                <div id="pfProgressWrap" class="hidden rounded-xl bg-slate-800 p-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span id="pfProgressLabel" class="text-slate-300"></span>
                        <span id="pfSisaCoverLabel" class="font-bold text-emerald-400"></span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-700">
                        <div id="pfProgressBar" class="h-3 rounded-full bg-emerald-500 transition-all duration-500" style="width:0%"></div>
                    </div>
                    <div id="pfProgressPct" class="text-xs text-slate-400"></div>
                </div>

                {{-- Detail tabel --}}
                <div class="overflow-x-auto rounded-xl border border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800/80">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No Mesin / Rangka</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Kode Model</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Nama SMH</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Harga SMH</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Gudang</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="pfDetailBody" class="divide-y divide-slate-800"></tbody>
                    </table>
                </div>
            </div>

            {{-- Ringkasan semua gudang --}}
            <div id="pfRingkasanWrap" class="hidden rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-3">
                <div class="text-sm font-semibold text-slate-200 mb-2">Ringkasan Semua Unit dalam Plan</div>
                <div class="overflow-x-auto rounded-xl border border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800/80">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Gudang / Unit</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Total Unit</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">Ditemukan</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Total Nilai SMH</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Plafon Cover</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Sisa Cover</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">% Terpakai</th>
                            </tr>
                        </thead>
                        <tbody id="pfRingkasanBody" class="divide-y divide-slate-800"></tbody>
                        <tfoot class="bg-slate-800/60">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-xs font-bold text-slate-300">TOTAL</td>
                                <td id="pfRingkasanTotalNilai" class="px-3 py-2 text-right text-xs font-bold text-blue-300"></td>
                                <td id="pfRingkasanTotalPlafon" class="px-3 py-2 text-right text-xs font-bold text-slate-300"></td>
                                <td id="pfRingkasanTotalSisa" class="px-3 py-2 text-right text-xs font-bold text-emerald-400"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {{-- Progress total --}}
                <div id="pfRingkasanProgressWrap" class="hidden rounded-xl bg-slate-800 p-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span id="pfRingkasanProgressLabel" class="text-slate-300"></span>
                        <span id="pfRingkasanSisaLabel" class="font-bold text-emerald-400">Sisa Cover</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-700">
                        <div id="pfRingkasanBar" class="h-3 rounded-full bg-blue-500 transition-all duration-500" style="width:0%"></div>
                    </div>
                    <div id="pfRingkasanPct" class="text-xs text-slate-400"></div>
                </div>
            </div>

        </div>
