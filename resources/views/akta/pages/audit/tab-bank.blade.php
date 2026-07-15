        <div id="tabPanel-bank" class="audit-tab-panel hidden space-y-5">
            <input type="hidden" id="bankPlanAuditId">

            {{-- Daftar kartu bank (di-generate via JS) --}}
            <div id="bankList" class="space-y-5"></div>

            <button type="button" id="addBankBtn"
                class="w-full rounded-2xl border-2 border-dashed border-blue-400 px-4 py-3 text-sm font-semibold text-blue-500 hover:bg-blue-50/5">
                + Tambah Bank
            </button>

            {{-- Register Cek yang belum digunakan --}}
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow">
                <div class="bg-[#1e3a5f] px-5 py-3 text-sm font-bold uppercase tracking-wide text-white">🧾 Register Cek/Giro yang Belum Digunakan</div>
                <div class="space-y-3 p-5">
                    <table class="w-full text-sm">
                        <tbody id="registerCekBody"></tbody>
                    </table>
                    <button type="button" data-add="registerCek" class="add-row-btn rounded-lg border border-dashed border-slate-400 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">+ Tambah Register Cek</button>
                </div>
            </div>

            {{-- Aksi simpan --}}
            <div class="flex justify-end gap-3">
                <button type="button" id="saveBankFormBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan Pemeriksaan Bank
                </button>
            </div>
        </div>
