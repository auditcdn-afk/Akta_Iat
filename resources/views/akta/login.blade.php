<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIMPAS-IAT</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SIMPAS-IAT">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/akta-auth.js'])
</head>

<body class="min-h-full bg-slate-950 text-slate-100">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        {{-- Dekorasi latar: glow lembut + grid halus, murni visual (tidak menutupi interaksi) --}}
        <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(148,163,184,0.15)_1px,transparent_0)] bg-[length:32px_32px] [mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_70%)]"></div>

        <section class="relative w-full max-w-md">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-5 flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl bg-white shadow-xl shadow-blue-600/30 ring-4 ring-blue-500/10">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-full w-full object-contain p-2"
                        onerror="this.parentElement.classList.add('bg-blue-600'); this.replaceWith(Object.assign(document.createElement('span'), {className: 'text-2xl font-black tracking-tight text-white', textContent: 'S'}));">
                </div>

                <h1 class="bg-gradient-to-r from-white to-slate-300 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                    SIMPAS-IAT
                </h1>

                <p class="mt-2 text-sm text-slate-400">
                    Aplikasi Audit Honda Dealer
                </p>
            </div>

            <div
                class="relative rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-8">
                <div id="loginAlert"
                    class="mb-4 hidden rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                </div>

                <form id="aktaLoginForm" class="space-y-5">
                    <div>
                        <label for="username" class="mb-2 block text-sm font-medium text-slate-200">
                            Username
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </span>
                            <input id="username" name="username" type="text" autocomplete="username" required autofocus
                                class="block w-full rounded-xl border border-slate-700 bg-slate-950 py-3 pl-11 pr-4 text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                                placeholder="Masukkan username">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-200">
                            Password
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                            <input id="password" name="password" type="password" autocomplete="current-password" required
                                class="block w-full rounded-xl border border-slate-700 bg-slate-950 py-3 pl-11 pr-11 text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                                placeholder="••••••••">
                            <button type="button" id="togglePassword" tabindex="-1"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-500 transition hover:text-slate-300">
                                <svg id="eyeIconShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg id="eyeIconHide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button id="loginButton" type="submit"
                        class="group flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-500 hover:shadow-lg hover:shadow-blue-600/30 disabled:cursor-not-allowed disabled:opacity-60">
                        <span>Masuk</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>

                <div class="mt-6 flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-3 text-xs text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286z" />
                    </svg>
                    <span>Sistem internal — hanya untuk personel berwenang.</span>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-600">
                &copy; {{ date('Y') }} SIMPAS-IAT &middot; Internal Audit Team
            </p>
        </section>
    </main>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js').catch(() => {});
            });
        }

        document.getElementById('togglePassword')?.addEventListener('click', () => {
            const input = document.getElementById('password');
            const show = document.getElementById('eyeIconShow');
            const hide = document.getElementById('eyeIconHide');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            show.classList.toggle('hidden', isHidden);
            hide.classList.toggle('hidden', !isHidden);
        });
    </script>
</body>

</html>
