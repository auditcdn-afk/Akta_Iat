<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $notifications = AppNotification::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn(AppNotification $notification) => $notification->toAktaArray());

        $unreadCount = AppNotification::query()
            ->where('user_id', $userId)
            ->unread()
            ->count();

        return response()->json([
            'ok' => true,
            'data' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()?->id) {
            return response()->json(['ok' => false, 'message' => 'Notifikasi ini bukan milik Anda.'], 403);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()?->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
