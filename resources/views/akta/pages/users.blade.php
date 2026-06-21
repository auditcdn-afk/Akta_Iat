@extends('akta.layouts.app')

@section('title', 'Pengguna - AKTA IAT')
@section('page_title', 'Pengguna')
@section('page_description', 'Manajemen user, role, unit usaha, dan status akun')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Daftar Pengguna</h2>
            <p class="mt-1 text-sm text-slate-400">
                Kelola akses admin, manajer, auditor, dan viewer.
            </p>
        </div>

        <button id="openCreateUserButton" type="button"
            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
            Tambah User
        </button>
    </div>

    <div id="userAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th
                            class="w-[36%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            User</th>
                        <th
                            class="w-[16%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Role</th>
                        <th
                            class="w-[16%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Unit</th>
                        <th
                            class="w-[16%] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Status</th>
                        <th
                            class="w-[16%] px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Aksi</th>
                    </tr>
                </thead>

                <tbody id="usersTableBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat data pengguna...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="userModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-8">
    <div class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
            <div>
                <h3 id="userModalTitle" class="text-lg font-bold">Tambah User</h3>
                <p class="text-sm text-slate-400">Isi data akun AKTA IAT.</p>
            </div>

            <button id="closeUserModalButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Tutup
            </button>
        </div>

        <form id="userForm" class="space-y-4 px-5 py-5">
            <input type="hidden" id="userId">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Username</label>
                    <input id="username" type="text" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Nama</label>
                    <input id="name" type="text" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Display Name</label>
                    <input id="displayName" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Email</label>
                    <input id="email" type="email"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Role</label>
                    <select id="role" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                        <option value="admin">Admin</option>
                        <option value="manajer">Manajer</option>
                        <option value="auditor">Auditor</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Unit Usaha</label>
                    <input id="unitUsaha" type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"
                        placeholder="HO / AUDIT / Cabang">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Password</label>
                    <input id="password" type="password"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"
                        placeholder="Minimal 8 karakter">
                    <p class="mt-1 text-xs text-slate-500">Kosongkan saat edit jika tidak ingin mengganti password.</p>
                </div>

                <div class="flex items-center rounded-xl border border-slate-800 bg-slate-950 px-3 py-2">
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input id="isDisabled" type="checkbox" class="rounded border-slate-700 bg-slate-900">
                        Nonaktifkan akun
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" id="cancelUserFormButton"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    Batal
                </button>

                <button type="submit" id="saveUserButton"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/akta-users.js')
@endpush