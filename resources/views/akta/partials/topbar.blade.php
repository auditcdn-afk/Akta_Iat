<header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="min-w-0">
            <h1 class="truncate text-base font-bold sm:text-lg">
                @yield('page_title', 'Dashboard')
            </h1>
            <p class="hidden truncate text-xs text-slate-400 sm:block">
                @yield('page_description', 'Migrasi Laravel 13 AKTA IAT')
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="hidden text-right sm:block">
                <div id="topbarUserName" class="text-sm font-semibold">Memuat...</div>
                <div id="topbarUserRole" class="text-xs text-slate-400">-</div>
            </div>

            <button id="shellLogoutButton" type="button"
                class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-red-500 hover:bg-red-500/10 hover:text-red-200">
                Logout
            </button>
        </div>
    </div>
</header>