@extends('akta.layouts.app')

@section('title', 'Monitoring - AKTA IAT')
@section('page_title', 'Monitoring')
@section('page_description', 'Statistik aplikasi, health check, dan log aktivitas')

@section('content')
<section class="space-y-5">
    <div
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold">Monitoring Admin</h2>
            <p class="mt-1 text-sm text-slate-400">
                Pantau kondisi aplikasi, data, user, dan aktivitas terakhir.
            </p>
        </div>

        <button id="refreshMonitoringButton" type="button"
            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">
            Refresh
        </button>
    </div>

    <div id="monitoringAlert" class="hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Total User</p>
            <p id="statTotalUsers" class="mt-2 text-3xl font-bold text-blue-400">-</p>
            <p id="statUserDetail" class="mt-2 text-xs text-slate-500">-</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Admin Aktif</p>
            <p id="statAdmins" class="mt-2 text-3xl font-bold text-red-300">-</p>
            <p class="mt-2 text-xs text-slate-500">Role dengan akses penuh.</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Data Key</p>
            <p id="statDataKeys" class="mt-2 text-3xl font-bold text-violet-400">-</p>
            <p id="statDataUpdated" class="mt-2 text-xs text-slate-500">-</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Activity Log</p>
            <p id="statActivityLogs" class="mt-2 text-3xl font-bold text-emerald-400">-</p>
            <p id="statLastActivity" class="mt-2 text-xs text-slate-500">-</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:col-span-1">
            <div class="mb-4">
                <h3 class="font-bold">Health Check</h3>
                <p class="text-sm text-slate-400">Status koneksi dasar aplikasi.</p>
            </div>

            <div id="healthCheckList" class="space-y-3">
                <div class="rounded-xl bg-slate-950 p-4 text-sm text-slate-400">
                    Memuat health check...
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 xl:col-span-2">
            <div class="mb-4">
                <h3 class="font-bold">System Info</h3>
                <p class="text-sm text-slate-400">Informasi runtime lokal Laravel.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">App</div>
                    <div id="sysApp" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">Environment</div>
                    <div id="sysEnvironment" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">PHP</div>
                    <div id="sysPhp" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">Laravel</div>
                    <div id="sysLaravel" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">Database</div>
                    <div id="sysDatabase" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs text-slate-500">Cache / Queue</div>
                    <div id="sysCacheQueue" class="mt-1 text-sm font-semibold text-slate-200">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div
            class="flex flex-col gap-3 border-b border-slate-800 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-bold">Activity Log Terbaru</h3>
                <p class="text-sm text-slate-400">Aktivitas login, user management, dan update data.</p>
            </div>

            <select id="activityLimit"
                class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
                <option value="10">10 log</option>
                <option value="25" selected>25 log</option>
                <option value="50">50 log</option>
                <option value="100">100 log</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Waktu</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Action</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Resource</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Detail</th>
                    </tr>
                </thead>

                <tbody id="activityLogBody" class="divide-y divide-slate-800">
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">
                            Memuat activity log...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@push('scripts')
@vite('resources/js/akta-monitoring.js')
@endpush