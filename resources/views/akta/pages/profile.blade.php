@extends('akta.layouts.app')

@section('title', 'Akun Saya - SIMPAS-IAT')
@section('page_title', 'Akun Saya')
@section('page_description', 'Kelola foto, identitas, dan keamanan akun Anda')

@section('content')
<section class="mx-auto max-w-3xl space-y-5">

    <div id="profileAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    {{-- ── Foto Profil ── --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-lg font-bold">Foto Profil</h2>
        <p class="mt-1 text-sm text-slate-400">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</p>

        <div class="mt-4 flex items-center gap-5">
            <span id="profileAvatar"
                class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-2xl font-bold text-white">
                <span id="profileAvatarInitial">U</span>
            </span>

            <div class="flex flex-wrap gap-3">
                <label
                    class="cursor-pointer rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                    Unggah Foto
                    <input id="photoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden">
                </label>
                <button id="deletePhotoButton" type="button"
                    class="rounded-xl border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                    Hapus Foto
                </button>
            </div>
        </div>
    </div>

    {{-- ── Identitas ── --}}
    <form id="identityForm" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-lg font-bold">Identitas Akun</h2>
        <p class="mt-1 text-sm text-slate-400">Username, role, dan wilayah hanya bisa diubah oleh admin.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Username</label>
                <input id="username" type="text" readonly
                    class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-400 outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Role</label>
                <input id="role" type="text" readonly
                    class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm capitalize text-slate-400 outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Nama Lengkap</label>
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
                <label class="mb-1 block text-sm font-medium text-slate-300">Unit Usaha</label>
                <input id="unitUsaha" type="text" readonly
                    class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-400 outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Wilayah</label>
                <input id="wilayah" type="text" readonly
                    class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-400 outline-none">
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Simpan Identitas
            </button>
        </div>
    </form>

    {{-- ── Ganti Password ── --}}
    <form id="passwordForm" class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-lg font-bold">Ganti Password</h2>
        <p class="mt-1 text-sm text-slate-400">Demi keamanan, masukkan password lama untuk mengganti.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-300">Password Lama</label>
                <input id="currentPassword" type="password" required autocomplete="current-password"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Password Baru</label>
                <input id="newPassword" type="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter.</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-300">Ulangi Password Baru</label>
                <input id="confirmPassword" type="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
                Ganti Password
            </button>
        </div>
    </form>
</section>
@endsection

@push('scripts')
@vite('resources/js/akta-profile.js')
@endpush
