<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppData;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function stats(): JsonResponse
    {
        $roles = User::query()
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->orderBy('role')
            ->get()
            ->map(fn($row) => [
                'role' => $row->role,
                'total' => (int) $row->total,
            ]);

        $lastActivity = ActivityLog::query()
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'users' => [
                    'total' => User::query()->count(),
                    'active' => User::query()->where('is_disabled', false)->count(),
                    'disabled' => User::query()->where('is_disabled', true)->count(),
                    'admins' => User::query()->where('role', 'admin')->count(),
                    'roles' => $roles,
                ],
                'appData' => [
                    'totalKeys' => AppData::query()->count(),
                    'lastUpdatedAt' => optional(AppData::query()->max('updated_at'))->toString(),
                ],
                'activity' => [
                    'totalLogs' => ActivityLog::query()->count(),
                    'lastAction' => $lastActivity?->action,
                    'lastResource' => $lastActivity?->resource,
                    'lastAt' => optional($lastActivity?->timestamp)->toDateTimeString(),
                ],
                'system' => [
                    'app' => config('akta.app_name', 'AKTA IAT'),
                    'environment' => app()->environment(),
                    'debug' => (bool) config('app.debug'),
                    'php' => PHP_VERSION,
                    'laravel' => Application::VERSION,
                    'database' => config('database.default'),
                    'cache' => config('cache.default'),
                    'queue' => config('queue.default'),
                    'timezone' => config('app.timezone'),
                ],
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['ok'] === true);

        return response()->json([
            'ok' => $healthy,
            'checks' => $checks,
        ], $healthy ? 200 : 500);
    }

    public function activityLog(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 25);
        $limit = max(5, min($limit, 100));

        $logs = ActivityLog::query()
            ->when($request->filled('username'), function ($query) use ($request) {
                $query->where('username', 'like', '%' . $request->query('username') . '%');
            })
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', 'like', '%' . $request->query('action') . '%');
            })
            ->when($request->filled('resource'), function ($query) use ($request) {
                $query->where('resource', 'like', '%' . $request->query('resource') . '%');
            })
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn(ActivityLog $log) => [
                'id' => $log->id,
                'timestamp' => optional($log->timestamp)->toDateTimeString(),
                'username' => $log->username,
                'displayName' => $log->display_name,
                'role' => $log->role,
                'action' => $log->action,
                'resource' => $log->resource,
                'detail' => $log->detail,
                'ip' => $log->ip,
                'userAgent' => $log->user_agent,
            ]);

        return response()->json([
            'ok' => true,
            'data' => $logs,
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1 as ok');

            return [
                'ok' => true,
                'message' => 'Database terkoneksi.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'akta_health_check';
            Cache::put($key, 'ok', 10);

            return [
                'ok' => Cache::get($key) === 'ok',
                'message' => 'Cache aktif.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        $path = storage_path();

        return [
            'ok' => is_dir($path) && is_writable($path),
            'message' => is_writable($path)
                ? 'Storage writable.'
                : 'Storage tidak writable.',
        ];
    }
}
