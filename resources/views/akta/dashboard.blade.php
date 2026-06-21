<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AKTA IAT</title>

    @vite(['resources/css/app.css', 'resources/js/akta-dashboard.js'])
</head>

<body class="min-h-full bg-slate-950 text-slate-100">
    <div class="min-h-screen">
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        AKTA IAT
                    </h1>
                    <p class="text-xs text-slate-400">
                        Dashboard awal migrasi Laravel
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p id="userDisplayName" class="text-sm font-semibold text-slate-100">Memuat...</p>
                        <p id="userRole" class="text-xs text-slate-400">-</p>
                    </div>

                    <button id="logoutButton" type="button"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-red-500 hover:bg-red-500/10 hover:text-red-200">
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="mb-8">
                <h2 class="text-2xl font-bold">
                    Dashboard
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    Login Sanctum sudah aktif. Tahap berikutnya: sidebar 17 menu dan pemecahan dashboard lama.
                </p>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm text-slate-400">Status Auth</p>
                    <p id="authStatus" class="mt-2 text-2xl font-bold text-emerald-400">Memeriksa...</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm text-slate-400">API</p>
                    <p id="apiStatus" class="mt-2 text-2xl font-bold text-blue-400">-</p>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm text-slate-400">Data Store</p>
                    <p id="dataStoreStatus" class="mt-2 text-2xl font-bold text-violet-400">-</p>
                </div>
            </section>

            <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold">Session Debug</h3>
                        <p class="text-sm text-slate-400">
                            Ini hanya untuk validasi lokal.
                        </p>
                    </div>
                </div>

                <pre id="sessionDebug" class="overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-300"></pre>
            </section>
        </main>
    </div>
</body>

</html>