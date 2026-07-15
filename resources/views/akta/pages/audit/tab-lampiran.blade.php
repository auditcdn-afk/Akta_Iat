        <div id="tabPanel-lampiran" class="audit-tab-panel hidden space-y-5">

            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-100">📎 Lampiran Audit</h3>
                <div class="flex gap-2">
                    <button id="lampiranMergeBtn" type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500 transition">
                        🔗 Gabung jadi 1 PDF
                    </button>
                    <button id="lampiranDownloadBtn" type="button"
                        class="hidden flex items-center gap-1.5 rounded-xl bg-green-600 px-4 py-2 text-xs font-semibold text-white hover:bg-green-500 transition">
                        ⬇️ Download PDF Gabungan
                    </button>
                </div>
            </div>

            {{-- Stat cards --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-slate-100" id="lampiranStatTotal">0</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Total File</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-green-400" id="lampiranStatMerged">—</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Status Gabungan</div>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 text-center">
                    <div class="text-2xl font-bold text-blue-400" id="lampiranStatSize">0 KB</div>
                    <div class="mt-1 text-xs font-semibold uppercase text-slate-400">Total Ukuran</div>
                </div>
            </div>

            {{-- Dropzone upload --}}
            <div id="lampiranDropzone"
                class="cursor-pointer rounded-2xl border-2 border-dashed border-slate-600/60 bg-slate-800/20 p-8 text-center transition hover:border-blue-400 hover:bg-blue-900/10">
                <div class="mb-3 text-4xl">📁</div>
                <p class="text-sm font-semibold text-slate-300 mb-1">Drag &amp; drop file ke sini</p>
                <p class="text-xs text-slate-400 mb-4">Format didukung: <span class="text-blue-400">PDF, JPG, PNG, DOC, DOCX</span> — maks. 20 MB per file</p>
                <label class="inline-block cursor-pointer rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-500 transition">
                    📂 Pilih File
                    <input id="lampiranFileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple class="hidden">
                </label>
                <p id="lampiranUploadMsg" class="hidden mt-3 text-xs text-slate-400"></p>
            </div>

            {{-- Daftar file --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                    <span class="text-sm font-bold text-slate-200">📋 Daftar File Lampiran</span>
                    <span id="lampiranTableCount" class="rounded-full bg-blue-600/20 px-3 py-1 text-xs font-bold text-blue-300">0 File</span>
                </div>
                <div id="lampiranFileList" class="divide-y divide-slate-800/60 p-2">
                    <p class="px-4 py-6 text-center text-xs text-slate-500">Belum ada file — upload file di atas.</p>
                </div>
            </div>

            {{-- Info gabungan --}}
            <div id="lampiranMergedInfo" class="hidden rounded-2xl border border-green-700/40 bg-green-900/10 p-4 flex items-center gap-4">
                <div class="text-3xl">✅</div>
                <div>
                    <p class="text-sm font-bold text-green-300">PDF Gabungan Siap</p>
                    <p class="text-xs text-slate-400 mt-0.5">Semua file telah digabung menjadi 1 PDF. Klik tombol Download di atas.</p>
                </div>
            </div>

        </div>
