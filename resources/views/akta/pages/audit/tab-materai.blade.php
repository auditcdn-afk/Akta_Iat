        <div id="tabPanel-materai" class="audit-tab-panel hidden space-y-5">

            {{-- Import HTML --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">📄</div>
                    <div class="text-sm font-semibold text-slate-200">Import Database Meterai (File HTML/HTM)</div>
                </div>
                <div id="mtDropZone"
                    class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 text-center transition hover:border-blue-500 cursor-pointer">
                    <svg class="h-10 w-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-slate-400">Drag &amp; drop file <strong class="text-slate-200">.htm / .html</strong> dari MTP SPP ke sini, atau</p>
                    <label class="cursor-pointer rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-400">
                        📂 Pilih File HTML
                        <input id="mtFileInput" type="file" accept=".htm,.html,.xhtml" class="hidden">
                    </label>
                    <p id="mtFileLabel" class="hidden text-xs text-emerald-400"></p>
                </div>
                <div class="flex items-center gap-3">
                    <button id="mtUploadBtn" type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                        Import & Parse HTML
                    </button>
                    <span id="mtUploadMsg" class="hidden text-xs text-emerald-400"></span>
                </div>
            </div>

            {{-- Hasil Berita Acara --}}
            <div id="mtResultWrap" class="space-y-5"></div>

        </div>
