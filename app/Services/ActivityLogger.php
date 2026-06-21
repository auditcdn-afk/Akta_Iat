<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Throwable;

class ActivityLogger
{
    public function write(
        Request $request,
        string $action,
        string $resource,
        ?string $detail = null,
        mixed $user = null
    ): void {
        try {
            $currentUser = $user ?: $request->user();

            ActivityLog::query()->create([
                'timestamp' => now(),
                'username' => $currentUser?->username,
                'display_name' => $currentUser?->display_name ?? $currentUser?->name,
                'role' => $currentUser?->role,
                'action' => $action,
                'resource' => $resource,
                'detail' => $detail,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable) {
            // Log aktivitas tidak boleh membuat request utama gagal.
        }
    }
}
