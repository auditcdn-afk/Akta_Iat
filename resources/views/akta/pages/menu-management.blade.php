@extends('akta.layouts.app')

@section('title', 'Manajemen Menu - AKTA IAT')
@section('page_title', 'Manajemen Menu')
@section('page_description', 'Pengaturan tampilan sidebar dan akses menu')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Konfigurasi Menu</h2>
            <p class="mt-1 text-sm text-slate-400">
                Atur label, kode, urutan, visibilitas, dan status admin-only pada sidebar.
            </p>
        </div>

        <div class="flex gap-3">
            <button id="resetMenuButton" type="button"
                class="rounded-xl border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                Reset Default
            </button>

            <button id="saveMenuButton" type="button"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Simpan Menu
            </button>
        </div>
    </div>

    <div id="menuAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <div class="mb-4">
            <h3 class="font-bold">Daftar Menu</h3>
            <p class="text-sm text-slate-400">
                Route dan path dikunci dari config Laravel. Yang bisa diubah hanya tampilan dan akses.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th
                            class="w-[7%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Urut</th>
                        <th
                            class="w-[23%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Label</th>
                        <th
                            class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Kode</th>
                        <th
                            class="w-[25%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Route</th>
                        <th
                            class="w-[12%] px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Visible</th>
                        <th
                            class="w-[12%] px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Admin</th>
                        <th
                            class="w-[11%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Path</th>
                    </tr>
                </thead>

                <tbody id="menuTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat konfigurasi menu...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-5 text-sm text-amber-100">
        <p class="font-bold">Catatan keamanan</p>
        <p class="mt-1 text-amber-100/80">
            Menyembunyikan menu hanya mengatur tampilan. Proteksi asli tetap ada di backend melalui middleware role.
        </p>
    </div>
</section>
@endsection

@push('scripts')
@vite('resources/js/akta-menu-management.js')
@endpush