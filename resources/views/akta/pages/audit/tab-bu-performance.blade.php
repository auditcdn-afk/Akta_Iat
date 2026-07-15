        <div id="tabPanel-bu-performance" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">BU Performance</h3>
                <button id="bupTabTambahBtn" type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    + Input Data
                </button>
            </div>

            <div id="bupTabAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

            {{-- Form --}}
            <div id="bupTabForm" class="hidden rounded-2xl border border-slate-700 bg-slate-800/60 p-5 space-y-4">
                <h4 class="text-sm font-bold text-slate-200">Input Penilaian Personil</h4>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-blue-400">Bulan <span class="text-red-400">*</span></label>
                    <input id="bupTabBulan" type="month"
                        class="w-64 rounded-xl border border-slate-600 bg-slate-900 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left w-36">Unit Usaha</th>
                                <th class="px-3 py-2 text-left w-28">Auditor</th>
                                <th class="px-3 py-2 text-left w-32">PIC</th>
                                <th class="px-3 py-2 text-left w-28">Jabatan</th>
                                <th class="px-3 py-2 text-left">Uraian</th>
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="bupTabInputBody" class="divide-y divide-slate-700"></tbody>
                    </table>
                </div>

                <div class="flex gap-3">
                    <button id="bupTabAddRowBtn" type="button"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-xs text-slate-300 hover:bg-slate-700 transition">
                        + Tambah Baris
                    </button>
                </div>

                <div class="flex gap-3 justify-end border-t border-slate-700 pt-3">
                    <button id="bupTabCancelBtn" type="button"
                        class="rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition">
                        Batal
                    </button>
                    <button id="bupTabSaveBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
                        Simpan
                    </button>
                </div>
            </div>

            {{-- Tabel output bergaya Excel --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-800">
                            <tr>
                                <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-400 border-r border-slate-700">Unit Usaha</th>
                                <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-400 border-r border-slate-700">Auditor</th>
                                <th colspan="3" class="px-4 py-2 text-center text-xs font-semibold text-slate-300 border-b border-slate-700">
                                    Penilaian Personil yang Kinerja Jelek (Sikap, Perilaku, Karakter, Kualitas)
                                </th>
                                <th rowspan="2" class="px-3 py-3 text-center text-xs text-slate-400 w-10"></th>
                            </tr>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-400 border-r border-slate-700">PIC</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-400 border-r border-slate-700">Jabatan</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-400 border-r border-slate-700">Uraian</th>
                            </tr>
                        </thead>
                        <tbody id="bupTabTableBody" class="divide-y divide-slate-800 text-slate-200">
                            <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
