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
