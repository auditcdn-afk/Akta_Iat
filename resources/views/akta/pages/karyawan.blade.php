@extends('akta.layouts.app')

@section('title', 'Data Karyawan - SIMPAS-IAT')
@section('page_title', 'Data Karyawan')
@section('page_description', 'Data karyawan per unit usaha: nama, jabatan, dan foto — bisa ditambah/dihapus kapan saja oleh unit usaha yang bersangkutan')

@push('scripts')
    @vite('resources/js/akta-karyawan.js')
@endpush

@section('content')
<section class="space-y-5">

    <div id="kryAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- Form Tambah Karyawan --}}
    <div id="kryFormCard" class="hidden rounded-2xl border border-slate-800 bg-slate-900 p-5 space-y-4">
        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-200">
            <span>🧑‍💼</span> Tambah Karyawan
        </h3>

        <form id="kryForm" class="space-y-4">
            <div id="kryUnitUsahaWrap" class="hidden">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Usaha <span class="text-red-400">*</span></label>
                <select id="kryUnitUsaha" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                    <option value="">Memuat daftar unit usaha...</option>
                </select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Nama <span class="text-red-400">*</span></label>
                    <input id="kryNama" type="text" required placeholder="Nama karyawan"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Jabatan <span class="text-red-400">*</span></label>
                    <input id="kryJabatan" type="text" required placeholder="Jabatan"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Foto <span class="normal-case font-normal text-slate-500">(opsional, maks 2MB)</span></label>
                <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">
                    <label class="cursor-pointer rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition shrink-0">
                        Pilih Foto
                        <input id="kryFileInput" type="file" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                    </label>
                    <span id="kryFileName" class="truncate text-sm text-slate-400">Belum ada foto</span>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-800 pt-4">
                <button type="submit" id="krySaveBtn"
                    class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                    💾 Simpan
                </button>
            </div>
        </form>
    </div>

    {{-- Filter unit usaha (HO roles) --}}
    <div id="kryFilterWrap" class="hidden flex items-center gap-2">
        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Filter Unit Usaha</label>
        <select id="kryFilterUnitUsaha" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <option value="">Semua Unit Usaha</option>
        </select>
    </div>

    {{-- Grid data karyawan --}}
    <div id="kryGrid" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        <p class="col-span-full text-center text-sm text-slate-500 py-8">Memuat data...</p>
    </div>
</section>
@endsection
