@php
$menuItems = app(\App\Services\AktaMenuService::class)->visibleItems();
@endphp

<aside id="aktaSidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-800 bg-slate-950 lg:block">
    <div class="flex h-full flex-col">
        <div class="border-b border-slate-800 px-5 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 font-black text-white">
                    A
                </div>

                <div>
                    <div class="font-bold leading-tight">AKTA IAT</div>
                    <div class="text-xs text-slate-400">Aplikasi Audit Honda Dealer</div>
                </div>
            </div>
        </div>

        <nav id="desktopSidebarNav" class="akta-scrollbar flex-1 space-y-1 overflow-y-auto px-3 pt-4 pb-28 scroll-pb-28"
            style="visibility: hidden;">
            @foreach ($menuItems as $item)
            @php
            $isActive = request()->routeIs($item['route']);
            @endphp

            <a href="{{ route($item['route']) }}" data-admin-only="{{ $item['admin_only'] ? 'true' : 'false' }}"
                data-active-menu="{{ $isActive ? 'true' : 'false' }}" class="akta-menu-item {{ $item['admin_only'] ? 'hidden' : '' }} group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{ $isActive
                        ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20'
                        : 'text-slate-300 hover:bg-slate-900 hover:text-white'
                    }}">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold
                        {{ $isActive ? 'bg-white/15 text-white' : 'bg-slate-900 text-slate-400 group-hover:bg-slate-800 group-hover:text-slate-200' }}">
                    {{ $item['code'] }}
                </span>

                <span class="truncate">
                    {{ $item['label'] }}
                </span>

                @if ($item['admin_only'])
                <span class="ml-auto rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                    ADMIN
                </span>
                @endif
            </a>
            @endforeach
        </nav>

        <script>
            (() => {
        const SESSION_KEY = 'akta_session';
        const SIDEBAR_SCROLL_KEY = 'akta_sidebar_scroll_top';

        const nav = document.getElementById('desktopSidebarNav');

        if (!nav) {
            return;
        }

        let role = null;

        try {
            const session = JSON.parse(sessionStorage.getItem(SESSION_KEY) || '{}');
            role = session?.user?.role || null;
        } catch {
            role = null;
        }

        const isAdmin = role === 'admin';

        document.querySelectorAll('[data-admin-only="true"]').forEach((element) => {
            if (isAdmin) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        });

        const savedScrollTop = Number(sessionStorage.getItem(SIDEBAR_SCROLL_KEY) || 0);

        if (savedScrollTop > 0) {
            nav.scrollTop = savedScrollTop;
        }

        const activeMenu = nav.querySelector('[data-active-menu="true"]');

        if (activeMenu) {
            const navRect = nav.getBoundingClientRect();
            const activeRect = activeMenu.getBoundingClientRect();

            const bottomSafeArea = 96;
            const isTooLow = activeRect.bottom > (navRect.bottom - bottomSafeArea);
            const isTooHigh = activeRect.top < navRect.top;

            if (isTooLow || isTooHigh) {
                const targetTop =
                    nav.scrollTop +
                    (activeRect.top - navRect.top) -
                    80;

                nav.scrollTop = Math.max(0, targetTop);
                sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(nav.scrollTop));
            }
        }

        nav.style.visibility = 'visible';
    })();
        </script>

        <div class="border-t border-slate-800 px-5 py-4">
            <div class="rounded-xl bg-slate-900 p-3">
                <div class="text-xs text-slate-400">Login sebagai</div>
                <div id="shellUserName" class="mt-1 truncate text-sm font-semibold">Memuat...</div>
                <div id="shellUserRole" class="mt-0.5 text-xs text-slate-500">-</div>
            </div>
        </div>
    </div>
</aside>

<div class="lg:hidden">
    <div class="border-b border-slate-800 bg-slate-950 px-4 py-3">
        <div class="flex items-center justify-between">
            <div>
                <div class="font-bold">AKTA IAT</div>
                <div class="text-xs text-slate-400">Audit Honda Dealer</div>
            </div>

            <button id="mobileMenuButton" type="button"
                class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200">
                Menu
            </button>
        </div>
    </div>

    <div id="mobileMenuPanel" data-scrollable-menu="true"
        class="akta-scrollbar hidden max-h-[70vh] overflow-y-auto border-b border-slate-800 bg-slate-950 px-3 py-3">
        <nav class="grid gap-1">
            @foreach ($menuItems as $item)
            @php
            $isActive = request()->routeIs($item['route']);
            @endphp

            <a href="{{ route($item['route']) }}" data-admin-only="{{ $item['admin_only'] ? 'true' : 'false' }}"
                data-active-menu="{{ $isActive ? 'true' : 'false' }}" class="akta-menu-item {{ $item['admin_only'] ? 'hidden' : '' }} flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium
                    {{ $isActive ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-900' }}">
                <span>{{ $item['label'] }}</span>

                @if ($item['admin_only'])
                <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-300">
                    ADMIN
                </span>
                @endif
            </a>
            @endforeach
        </nav>
    </div>
</div>