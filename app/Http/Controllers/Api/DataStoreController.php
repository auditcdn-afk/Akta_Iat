<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\AppDataStore;
use App\Support\DataKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DataStoreController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => config('akta.app_name', 'AKTA IAT'),
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function allData(AppDataStore $store): JsonResponse
    {
        return response()->json($store->all());
    }

    public function read(string $key, AppDataStore $store): JsonResponse
    {
        if (! DataKeys::allowed($key)) {
            return response()->json([
                'error' => 'Data key tidak dikenal.',
                'key' => $key,
            ], 404);
        }

        return response()->json([
            'key' => $key,
            'data' => $store->read($key),
        ]);
    }

    public function write(
        Request $request,
        AppDataStore $store,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable'],
        ]);

        $key = $payload['key'];

        if (! DataKeys::allowed($key)) {
            return response()->json([
                'error' => 'Data key tidak dikenal.',
                'key' => $key,
            ], 422);
        }

        try {
            $value = $store->write(
                $key,
                $payload['value'] ?? [],
                $request->user()?->username ?? 'guest'
            );

            $logger->write(
                $request,
                'DATA_WRITE',
                $key,
                'Update data key: ' . $key
            );

            return response()->json([
                'ok' => true,
                'key' => $key,
                'data' => $value,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Gagal menyimpan data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
