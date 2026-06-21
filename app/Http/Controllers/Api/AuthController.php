<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = User::query()
            ->where('username', $payload['username'])
            ->first();

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            $logger->write(
                $request,
                'LOGIN_FAILED',
                'auth',
                'Login gagal untuk username: ' . $payload['username']
            );

            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if ($user->is_disabled) {
            $logger->write(
                $request,
                'LOGIN_BLOCKED',
                'auth',
                'Login ditolak karena user disabled: ' . $user->username,
                $user
            );

            return response()->json([
                'message' => 'Akun ini dinonaktifkan.',
            ], 403);
        }

        // Hapus token lama milik user agar lokal tetap bersih.
        // Nanti kalau ingin multi-device, bagian ini bisa dihapus.
        $user->tokens()->delete();

        $token = $user->createToken(config('akta.token_name', 'akta-iat-token'))->plainTextToken;

        $logger->write(
            $request,
            'LOGIN',
            'auth',
            'Login berhasil: ' . $user->username,
            $user
        );

        return response()->json([
            'ok' => true,
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $user->toAktaArray(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'user' => $request->user()->toAktaArray(),
        ]);
    }

    public function logout(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();

        $request->user()?->currentAccessToken()?->delete();

        $logger->write(
            $request,
            'LOGOUT',
            'auth',
            'Logout: ' . $user?->username,
            $user
        );

        return response()->json([
            'ok' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
}
