<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AKTA IAT</title>

    @vite(['resources/css/app.css', 'resources/js/akta-auth.js'])
</head>

<body class="min-h-full bg-slate-950 text-slate-100">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/20">
                    <span class="text-2xl font-black tracking-tight">A</span>
                </div>

                <h1 class="text-3xl font-bold tracking-tight">
                    AKTA IAT
                </h1>

                <p class="mt-2 text-sm text-slate-400">
                    Aplikasi Audit Honda Dealer
                </p>
            </div>

            <div
                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-black/30 backdrop-blur">
                <div id="loginAlert"
                    class="mb-4 hidden rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                </div>

                <form id="aktaLoginForm" class="space-y-5">
                    <div>
                        <label for="username" class="mb-2 block text-sm font-medium text-slate-200">
                            Username
                        </label>
                        <input id="username" name="username" type="text" autocomplete="username" required autofocus
                            class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                            placeholder="admin">
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-200">
                            Password
                        </label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                            placeholder="••••••••">
                    </div>

                    <button id="loginButton" type="submit"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                        Masuk
                    </button>
                </form>

                <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/70 p-4 text-xs text-slate-400">
                    <p class="font-semibold text-slate-300">Akun lokal:</p>
                    <p class="mt-1">admin / admin12345</p>
                    <p>auditor / auditor12345</p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-500">
                Local Development • Laravel 13 • Sanctum
            </p>
        </section>
    </main>
</body>

</html>