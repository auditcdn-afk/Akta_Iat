<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('role')
            ->orderBy('username')
            ->get()
            ->map(fn(User $user) => $this->formatUser($user));

        return response()->json([
            'ok' => true,
            'data' => $users,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $request->validate([
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(config('akta.roles', ['admin', 'manajer', 'auditor', 'viewer']))],
            'unit_usaha' => ['nullable', 'string', 'max:100'],
            'is_disabled' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'username' => $payload['username'],
            'name' => $payload['name'],
            'display_name' => $payload['display_name'] ?: $payload['name'],
            'email' => $payload['email'] ?: $payload['username'] . '@akta.local',
            'password' => Hash::make($payload['password']),
            'role' => $payload['role'],
            'unit_usaha' => $payload['unit_usaha'] ?? '',
            'is_disabled' => (bool) ($payload['is_disabled'] ?? false),
            'created_by' => $request->user()?->username,
        ]);

        $logger->write(
            $request,
            'USER_CREATE',
            'users',
            'Membuat user: ' . $user->username,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'User berhasil dibuat.',
            'data' => $this->formatUser($user),
        ], 201);
    }

    public function update(Request $request, User $user, ActivityLogger $logger): JsonResponse
    {
        $payload = $request->validate([
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(config('akta.roles', ['admin', 'manajer', 'auditor', 'viewer']))],
            'unit_usaha' => ['nullable', 'string', 'max:100'],
            'is_disabled' => ['nullable', 'boolean'],
        ]);

        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id) {
            if (($payload['role'] ?? $user->role) !== 'admin') {
                return response()->json([
                    'message' => 'Admin tidak boleh menurunkan role akun sendiri.',
                ], 422);
            }

            if ((bool) ($payload['is_disabled'] ?? false) === true) {
                return response()->json([
                    'message' => 'Admin tidak boleh menonaktifkan akun sendiri.',
                ], 422);
            }
        }

        $user->fill([
            'username' => $payload['username'],
            'name' => $payload['name'],
            'display_name' => $payload['display_name'] ?: $payload['name'],
            'email' => $payload['email'] ?: $payload['username'] . '@akta.local',
            'role' => $payload['role'],
            'unit_usaha' => $payload['unit_usaha'] ?? '',
            'is_disabled' => (bool) ($payload['is_disabled'] ?? false),
        ]);

        if (! empty($payload['password'])) {
            $user->password = Hash::make($payload['password']);
            $user->tokens()->delete();
        }

        $user->save();

        $logger->write(
            $request,
            'USER_UPDATE',
            'users',
            'Update user: ' . $user->username,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'User berhasil diperbarui.',
            'data' => $this->formatUser($user),
        ]);
    }

    public function destroy(Request $request, User $user, ActivityLogger $logger): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'Admin tidak boleh menghapus akun sendiri.',
            ], 422);
        }

        if ($user->role === 'admin') {
            $adminCount = User::query()
                ->where('role', 'admin')
                ->where('is_disabled', false)
                ->count();

            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Minimal harus ada satu admin aktif.',
                ], 422);
            }
        }

        $username = $user->username;

        $user->tokens()->delete();
        $user->delete();

        $logger->write(
            $request,
            'USER_DELETE',
            'users',
            'Menghapus user: ' . $username,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'displayName' => $user->display_name,
            'email' => $user->email,
            'role' => $user->role,
            'unitUsaha' => $user->unit_usaha ?: '',
            'isDisabled' => (bool) $user->is_disabled,
            'createdBy' => $user->created_by,
            'createdAt' => optional($user->created_at)->toDateTimeString(),
            'updatedAt' => optional($user->updated_at)->toDateTimeString(),
        ];
    }
}
