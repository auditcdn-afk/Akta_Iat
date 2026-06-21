<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\AktaMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(AktaMenuService $menu): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $menu->items(),
        ]);
    }

    public function update(
        Request $request,
        AktaMenuService $menu,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $request->validate([
            'items' => ['required', 'array'],
            'items.*.route' => ['required', 'string'],
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.code' => ['required', 'string', 'max:3'],
            'items.*.admin_only' => ['nullable', 'boolean'],
            'items.*.visible' => ['nullable', 'boolean'],
            'items.*.order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $items = $menu->update(
            $payload['items'],
            $request->user()?->username
        );

        $logger->write(
            $request,
            'MENU_UPDATE',
            'menu',
            'Update konfigurasi menu sidebar.',
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Konfigurasi menu berhasil disimpan.',
            'data' => $items,
        ]);
    }

    public function reset(
        Request $request,
        AktaMenuService $menu,
        ActivityLogger $logger
    ): JsonResponse {
        $items = $menu->reset($request->user()?->username);

        $logger->write(
            $request,
            'MENU_RESET',
            'menu',
            'Reset konfigurasi menu ke default.',
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Konfigurasi menu dikembalikan ke default.',
            'data' => $items,
        ]);
    }
}
