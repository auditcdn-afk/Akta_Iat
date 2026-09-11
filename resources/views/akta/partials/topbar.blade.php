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

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <div class="relative">
                <button id="notifBellBtn" type="button"
                    class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-200 transition hover:border-blue-500 hover:bg-blue-500/10 hover:text-blue-200"
                    title="Notifikasi">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    <span id="notifBellBadge"
                        class="absolute -right-1 -top-1 hidden min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-bold leading-[1.1rem] text-white">0</span>
                </button>

                {{-- Di lebar HP panel ini dipaku ke layar (fixed), bukan digantung
                     pada tombol lonceng. Digantung pada tombolnya, panel selebar
                     320px ikut terdorong keluar layar mengikuti posisi lonceng —
                     isinya terpotong dan judul notifikasi mengular satu huruf per
                     baris. Mulai lebar sm ke atas, perilaku menggantung dipakai lagi. --}}
                <div id="notifDropdown"
                    class="fixed inset-x-3 top-[4.5rem] z-50 hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl
                           sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80">
                    <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                        <span class="text-sm font-semibold text-slate-100">Notifikasi</span>
                        <button id="notifMarkAllReadBtn" type="button" class="text-xs font-semibold text-blue-400 hover:text-blue-300">
                            Tandai semua terbaca
                        </button>
                    </div>
                    <div id="notifList" class="max-h-[55vh] overflow-y-auto akta-scrollbar sm:max-h-96">
                        <p class="px-4 py-6 text-center text-sm text-slate-400">Memuat...</p>
                    </div>

                    {{-- Notifikasi ke layar HP. Ditaruh di kaki dropdown, bukan di
                         halaman pengaturan tersendiri: di sinilah orang berada saat
                         terpikir "kenapa saya tidak diberi tahu?". --}}
                    <div class="border-t border-slate-800 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <button id="pushToggleBtn" type="button"
                                class="rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-blue-500 hover:bg-blue-500/10 hover:text-blue-200 disabled:opacity-50">
                                Aktifkan notifikasi HP
                            </button>
                            <button id="pushTestBtn" type="button"
                                class="hidden rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-semibold text-slate-400 transition hover:border-slate-500 hover:text-slate-200 disabled:opacity-50">
                                Coba kirim
                            </button>
                            {{-- Hanya muncul untuk admin, dan hanya selama fiturnya
                                 memang belum dinyalakan. Ditampilkan dari JS setelah
                                 server memastikan keduanya — bukan lewat data-roles,
                                 yang akan menampilkannya ke admin selamanya. --}}
                            <button id="pushActivateBtn" type="button"
                                class="hidden rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50">
                                Aktifkan untuk seluruh aplikasi
                            </button>
                        </div>
                        <p id="pushToggleHint" class="mt-2 text-[11px] leading-relaxed text-slate-500">
                            Dapatkan pemberitahuan di layar HP walau aplikasi tertutup.
                        </p>
                    </div>
                </div>
            </div>

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