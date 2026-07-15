        <div id="tabPanel-pica" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">🎯 PICA (Problem Identification &amp; Corrective Action)</h3>
                <button id="picaSaveBtn" type="button"
                    class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                    💾 Simpan PICA
                </button>
            </div>

            <p class="text-xs text-slate-400">
                Daftar item Grading dengan hasil bernilai rendah (pilihan nomor <span class="font-bold text-red-300">1</span> &amp;
                <span class="font-bold text-amber-300">2</span>) yang wajib dibuatkan PICA.
            </p>

            {{-- Stat cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="picaStatTotal">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Item PICA</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-400" id="picaStatFilled">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Sudah Diisi</div>
                </div>
            </div>

            {{-- List item PICA --}}
            <div id="picaList" class="space-y-3"></div>

            {{-- Empty / info state --}}
            <div id="picaEmpty" class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-10 text-center">
                <div class="text-4xl mb-2">✅</div>
                <p class="text-sm text-slate-400">Tidak ada item Grading dengan hasil nomor 1 atau 2.</p>
                <p class="text-xs text-slate-500 mt-1">PICA hanya muncul untuk hasil pemeriksaan dengan nilai rendah.</p>
            </div>

        </div>
