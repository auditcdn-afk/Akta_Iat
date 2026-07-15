        <div id="tabPanel-smh-tarikan" class="audit-tab-panel hidden space-y-5">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">🏍️ Pemeriksaan SMH Tarikan</h3>
                <div class="flex gap-2">
                    <button id="smhTarikanAddBtn" type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                        + Tambah Unit
                    </button>
                    <button id="smhTarikanSaveBtn" type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition">
                        💾 Simpan
                    </button>
                </div>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="smhTarikanStatTotal">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Total Unit</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-400" id="smhTarikanStatLengkap">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Data Lengkap</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-400" id="smhTarikanStatDraft">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Draft</div>
                </div>
            </div>

            {{-- Form Tambah / Edit (tersembunyi) --}}
            <div id="smhTarikanForm" class="hidden rounded-2xl border border-blue-700/40 bg-blue-900/10 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-sm font-bold uppercase tracking-wide text-blue-300">📝 <span id="smhTarikanFormTitle">Tambah Unit SMH Tarikan</span></h4>
                    <button id="smhTarikanFormClose" type="button" class="text-slate-400 hover:text-slate-200 text-lg leading-none">✕</button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <input type="hidden" id="smhTarikanFormIdx" value="-1">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Nama Konsumen <span class="text-red-400">*</span></label>
                        <input type="text" id="smhTarikanNama" placeholder="Nama konsumen..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">No. BAST <span class="text-red-400">*</span></label>
                        <input type="text" id="smhTarikanNoBast" placeholder="Nomor BAST..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Merk / Type</label>
                        <input type="text" id="smhTarikanMerk" placeholder="Contoh: Honda Beat 2022..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Tahun</label>
                        <input type="number" id="smhTarikanTahun" placeholder="Tahun kendaraan..." min="1990" max="2099"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Nomor Mesin</label>
                        <input type="text" id="smhTarikanNoMesin" placeholder="Nomor mesin..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none uppercase">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Nomor Rangka</label>
                        <input type="text" id="smhTarikanNoRangka" placeholder="Nomor rangka (VIN)..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none uppercase">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">No. Polisi</label>
                        <input type="text" id="smhTarikanNopol" placeholder="Contoh: B 1234 XYZ..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none uppercase">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">No. Kontrak</label>
                        <input type="text" id="smhTarikanNoKontrak" placeholder="Nomor kontrak..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-300">Sisa Piutang (Rp)</label>
                        <input type="text" inputmode="numeric" id="smhTarikanSisaPiutang" placeholder="0"
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Kondisi SMH <span class="text-slate-500">(opsional)</span></label>
                        <textarea id="smhTarikanKondisi" rows="3" placeholder="Deskripsikan kondisi kendaraan saat tarikan..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Perlengkapan <span class="text-slate-500">(opsional)</span></label>
                        <input type="text" id="smhTarikanPerlengkapan" placeholder="Contoh: STNK, BPKB, Kunci..."
                            class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Tanggal Pengajuan</label>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" id="smhTarikanSudahAjukan"
                                    class="rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-blue-500">
                                <span class="text-sm text-slate-300">Sudah diajukan</span>
                            </label>
                            <input type="date" id="smhTarikanTglPengajuan"
                                class="hidden rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-blue-400 focus:outline-none">
                            <span id="smhTarikanBelumAjukan" class="text-sm text-yellow-400 font-medium">Belum Ajukan</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button id="smhTarikanFormSave" type="button"
                        class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                        ✓ Simpan Unit
                    </button>
                    <button id="smhTarikanFormReset" type="button"
                        class="rounded-xl border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition">
                        Reset
                    </button>
                    <p id="smhTarikanFormMsg" class="text-xs font-medium"></p>
                </div>
            </div>

            {{-- Tabel --}}
            <div class="overflow-x-auto rounded-2xl border border-slate-700 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                    <span class="text-sm font-bold text-slate-200">📋 DATA SMH TARIKAN</span>
                    <span id="smhTarikanCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 Unit</span>
                </div>
                <table class="w-full min-w-[1100px] text-xs">
                    <thead class="border-b border-slate-700 bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-8">No.</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Nama Konsumen</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">No. BAST</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Merk / Type</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-12">Tahun</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">No. Mesin</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-28">No. Rangka</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-20">No. Polisi</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400 w-24">No. Kontrak</th>
                            <th class="px-3 py-2 text-right font-semibold uppercase text-slate-400 w-28">Sisa Piutang</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Kondisi SMH</th>
                            <th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">Perlengkapan</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-28">Tgl Pengajuan</th>
                            <th class="px-3 py-2 text-center font-semibold uppercase text-slate-400 w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="smhTarikanTableBody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>

        </div>
