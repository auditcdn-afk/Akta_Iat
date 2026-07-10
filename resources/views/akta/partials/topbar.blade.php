<header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="min-w-0">
            <h1 class="truncate text-base font-bold sm:text-lg">
                @yield('page_title', 'Dashboard')
            </h1>
            <p class="hidden truncate text-xs text-slate-400 sm:block">
                @yield('page_description', 'Migrasi Laravel 13 SIMPAS-IAT')
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <button id="themeToggleBtn" type="button" class="theme-toggle-btn" title="Ganti mode terang/gelap">
                <svg class="theme-toggle-icon-dark h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9-5.998Z" />
                </svg>
                <svg class="theme-toggle-icon-light h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
            </button>

            <a href="{{ route('akta.profile') }}"
                class="flex items-center gap-3 rounded-xl px-2 py-1.5 transition hover:bg-slate-800/60"
                title="Akun Saya">
                <div class="hidden text-right sm:block">
                    <div id="topbarUserName" class="text-sm font-semibold">Memuat...</div>
                    <div id="topbarUserRole" class="text-xs text-slate-400">-</div>
                </div>
                <span id="topbarAvatar"
                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-sm font-bold text-white">
                    <span id="topbarAvatarInitial">A</span>
                </span>
            </a>

            <button id="shellLogoutButton" type="button"
                class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-red-500 hover:bg-red-500/10 hover:text-red-200">
                Logout
            </button>
        </div>
    </div>
</header>