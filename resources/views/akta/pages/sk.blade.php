@extends('akta.layouts.app')

@section('title', 'SK - AKTA IAT')
@section('page_title', 'SK')
@section('page_description', 'Surat Keputusan audit dan alur persetujuan')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-bold">Daftar SK</h2>
            <p class="mt-1 text-sm text-slate-400">
                Kelola Surat Keputusan audit berdasarkan plan audit dan workflow approval.
            </p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row">
            <input id="skSearch" type="search" placeholder="Cari no SK / no SPT / unit usaha..."
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 lg:w-72">

            <select id="skStatusFilter"
                class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="">Semua Status</option>
                <option value="pending_manajer">Pending Manajer</option>
                <option value="pending_afd">Pending AFD</option>
                <option value="selesai">Selesai</option>
                <option value="ditolak">Ditolak</option>
            </select>

            <button id="openCreateSkButton" type="button"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Tambah
            </button>
        </div>
    </div>

    <div id="skAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</div>
            <div id="skTotalStat" class="mt-2 text-2xl font-bold text-slate-100">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Manajer</div>
            <div id="skPendingManajerStat" class="mt-2 text-2xl font-bold text-blue-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending AFD</div>
            <div id="skPendingAfdStat" class="mt-2 text-2xl font-bold text-amber-300">0</div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selesai</div>
            <div id="skSelesaiStat" class="mt-2 text-2xl font-bold text-emerald-300">0</div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
        <label class="mb-2 block text-sm font-medium text-slate-300">Filter Plan Audit</label>
        <select id="skPlanFilter"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Plan Audit</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            SK
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Plan Audit
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            File
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Uploaded
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Status
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Progress
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody id="skTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat SK...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="myDistribusiSection" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <h3 class="text-lg font-bold">SK Diterima Saya</h3>
        <p class="mt-1 text-sm text-slate-400">Surat Keputusan yang didistribusikan kepada Anda — berikan tanggapan bila diperlukan.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left">
                <thead>
                    <tr class="border-b border-slate-800">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No SK</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Usaha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">File</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Memutuskan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggapan</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody id="myDistribusiTableBody" class="divide-y divide-slate-800"></tbody>
            </table>
        </div>
    </div>
</section>

<div id="skModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div
        class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="skModalTitle" class="text-lg font-bold">Tambah SK</h3>
                <p class="text-sm text-slate-400">SK dapat dihubungkan dengan Plan Audit.</p>
            </div>

            <button id="closeSkModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="skForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="skId">

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Plan Audit</label>
                    <select id="planAuditId"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="">Tanpa Plan Audit</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">No SK</label>
                    <input id="noSk" type="text" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">No SPT</label>
                    <input id="noSpt" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Unit Usaha</label>
                    <input id="unitUsaha" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Jenis Audit</label>
                    <input id="jenisAudit" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Surat Keputusan (PDF)</label>
                    <input id="skFile" type="file" accept="application/pdf"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-white file:text-xs file:font-semibold">
                    <p id="skFileExisting" class="mt-1 text-xs text-slate-500"></p>
                </div>

            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelSkFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    Batal
                </button>

                <button type="submit" id="saveSkButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div id="resubmitSkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Upload Ulang SK</h3>
                <p class="text-sm text-slate-400">SK ditolak — unggah file PDF pengganti untuk diajukan ulang.</p>
            </div>
            <button type="button" id="closeResubmitSkModalBtn"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>

        <form id="resubmitSkForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="resubmitSkId">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Surat Keputusan (PDF) <span class="text-red-400">*</span></label>
                <input id="resubmitSkFile" type="file" accept="application/pdf" required
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-white file:text-xs file:font-semibold">
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelResubmitSkBtn"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button type="submit" id="saveResubmitSkBtn"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="distributeSkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Distribusikan SK</h3>
                <p class="text-sm text-slate-400">Pilih satu atau lebih pengguna penerima.</p>
            </div>
            <button type="button" id="closeDistributeSkModalBtn"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>

        <form id="distributeSkForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="distributeSkId">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Memutuskan</label>
                <textarea id="distributeSkMemutuskan" rows="16" placeholder="Salin poin-poin &quot;Memutuskan&quot; dari dokumen SK di sini..."
                    class="w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm leading-relaxed text-slate-100 outline-none focus:border-blue-500"></textarea>
                <p class="mt-1 text-xs text-slate-500">Poin ini akan ditampilkan kepada pengguna yang dipilih di bawah.</p>
            </div>
            <div id="distributeSkUserList" class="max-h-56 space-y-2 overflow-y-auto pr-1"></div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelDistributeSkBtn"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button type="submit" id="saveDistributeSkBtn"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Distribusikan</button>
            </div>
        </form>
    </div>
</div>

<div id="tanggapiSkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Tanggapan SK</h3>
                <p class="text-sm text-slate-400">Centang setiap poin yang sudah diselesaikan, beri catatan bila perlu.</p>
            </div>
            <button type="button" id="closeTanggapiSkModalBtn"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>

        <form id="tanggapiSkForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="tanggapiSkId">

            <div id="tanggapiSkPoinList" class="hidden space-y-3 max-h-96 overflow-y-auto pr-1"></div>

            <div id="tanggapiSkOverallWrap" class="hidden">
                <label class="mb-1 block text-sm font-medium text-slate-300">Tanggapan <span class="text-red-400">*</span></label>
                <textarea id="tanggapiSkText" rows="5"
                    class="w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"></textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Lampiran File (opsional)</label>
                <input id="tanggapiSkFile" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-white file:text-xs file:font-semibold">
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelTanggapiSkBtn"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Batal</button>
                <button type="submit" id="saveTanggapiSkBtn"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="pembebananSkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="text-lg font-bold">Pembebanan SK</h3>
                <p class="text-sm text-slate-400">Rincian pembebanan personil berdasarkan Surat Keputusan.</p>
            </div>
            <button type="button" id="closePembebananSkModalBtn"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Tutup</button>
        </div>

        <form id="pembebananSkForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="pembebananSkId">
            <input type="hidden" id="pembebananPlanId">

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Tgl Audit</label>
                    <input id="pembebananTglAudit" type="date"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">No SK</label>
                    <input id="pembebananNoSk" type="text" readonly
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-400 outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Unit Usaha</label>
                    <input id="pembebananUnitUsaha" type="text" readonly
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-400 outline-none">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Nama Pimpinan SO</label>
                    <input id="pembebananPimpinanSo" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Nama Pimpinan CSC</label>
                    <input id="pembebananPimpinanCsc" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
            </div>

            <div id="pembebananSudahDisimpanWrap" class="hidden">
                <label class="mb-2 block text-sm font-medium text-slate-300">Personil Sudah Disimpan</label>
                <div id="pembebananSudahDisimpanList" class="space-y-2"></div>
                <div class="mt-2 flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                    <span class="text-sm font-semibold text-slate-300">Total Pembebanan</span>
                    <span id="pembebananTotalDisplay" class="text-lg font-bold text-emerald-300">Rp 0</span>
                </div>
                <div id="pembebananFinalizeWrap" class="mt-2"></div>
            </div>

            <div id="personilEntrySection">
                <label class="mb-2 block text-sm font-medium text-slate-300">Tambah Personil</label>
                <div id="personilList"></div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelPembebananSkBtn"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">Tutup</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-sk.js')
@endpush
