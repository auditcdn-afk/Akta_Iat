        <div class="flex items-center justify-between rounded-2xl border border-slate-700 bg-slate-900 px-5 py-3">
            <div>
                <h3 class="font-bold text-slate-100">Pemeriksaan: <span id="pemeriksaanPlanLabel" class="text-blue-300">-</span></h3>
                <p class="text-xs text-slate-500 mt-0.5">Pilih jenis pemeriksaan di bawah ini</p>
            </div>
            <button type="button" id="closePemeriksaanBtn"
                class="text-xs text-slate-400 hover:text-slate-200 border border-slate-700 rounded-lg px-3 py-1.5">
                Tutup
            </button>
        </div>

        {{-- Nama Auditor & Nama Auditee — satu pasang per (plan, tool aktif). Nama
             Auditor otomatis dari akun login (readonly); Nama Auditee wajib diketik
             manual. Isinya disegarkan tiap kali tab pemeriksaan berganti — lihat
             loadAuditorWidget() di audit-editor.js. Menyimpan tool apa pun (Kas,
             Bank, dst) akan ditolak server selama pasangan ini untuk tool tersebut
             belum tersimpan. --}}
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Data Auditor — <span id="auditorWidgetToolLabel">-</span>
                </p>
                <p id="auditorWidgetStatus" class="text-xs text-emerald-400 hidden">Tersimpan</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <div>
                    <label class="mb-1 block text-xs text-slate-500">Nama Auditor</label>
                    <input id="auditorWidgetAuditor" type="text" readonly
                        class="w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/50 px-3 py-2 text-sm text-slate-300 outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">Nama Auditee <span class="text-red-400">*</span></label>
                    <input id="auditorWidgetAuditee" type="text" placeholder="Nama pihak yang diperiksa..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="button" id="auditorWidgetSaveBtn"
                        class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 sm:w-auto">
                        Simpan
                    </button>
                </div>
            </div>
        </div>

        {{-- Banner Perbaikan (muncul jika plan berstatus revisi / Not OK dari penilaian) --}}
        <div id="revisiPemeriksaanBanner" class="hidden rounded-2xl border border-red-500/30 bg-red-500/5 p-5 space-y-3">
            <div class="text-sm text-red-200">
                Penilaian menyatakan <strong>Not OK</strong>. Perbaiki pemeriksaan pada tab-tab di bawah ini,
                lalu isi tanggapan perbaikan dan tekan <strong>Selesai</strong>.
            </div>
            <div>
                <label for="revisiPemeriksaanCatatan" class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Tanggapan Perbaikan</label>
                <textarea id="revisiPemeriksaanCatatan" rows="3"
                    class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100"
                    placeholder="Jelaskan perbaikan yang sudah dilakukan..."></textarea>
            </div>
            <div class="flex justify-end">
                <button type="button" id="revisiPemeriksaanSelesaiBtn"
                    class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                    Selesai
                </button>
            </div>
        </div>
