<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ok'   => true,
            'data' => Role::orderBy('order')->get()->map->toAktaArray(),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:50', 'alpha_dash', 'unique:roles,name'],
            'label'       => ['required', 'string', 'max:100'],
            'color'       => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'order'       => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $role = Role::create([
            'name'        => strtolower($validated['name']),
            'label'       => $validated['label'],
            'color'       => $validated['color'] ?? 'slate',
            'description' => $validated['description'] ?? null,
            'is_system'   => false,
            'order'       => $validated['order'] ?? 99,
        ]);

        $logger->write($request, 'ROLE_CREATE', 'roles', "Tambah role: {$role->name}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Role '{$role->label}' berhasil ditambahkan.",
            'data'    => $role->toAktaArray(),
        ], 201);
    }

    public function update(Request $request, Role $role, ActivityLogger $logger): JsonResponse
    {
        $validated = $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'color'       => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'order'       => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        // name (slug) tidak boleh diubah karena sudah dipakai di users.role
        $role->update([
            'label'       => $validated['label'],
            'color'       => $validated['color'] ?? $role->color,
            'description' => $validated['description'] ?? $role->description,
            'order'       => $validated['order'] ?? $role->order,
        ]);

        $logger->write($request, 'ROLE_UPDATE', 'roles', "Update role: {$role->name}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Role '{$role->label}' berhasil diperbarui.",
            'data'    => $role->fresh()->toAktaArray(),
        ]);
    }

    public function destroy(Request $request, Role $role, ActivityLogger $logger): JsonResponse
    {
        // Cegah hapus role sistem (admin, auditor, dll)
        if ($role->is_system) {
            return response()->json([
                'ok'      => false,
                'message' => "Role '{$role->label}' adalah role sistem dan tidak dapat dihapus.",
            ], 422);
        }

        // Cegah hapus role yang masih dipakai user
        $inUse = \App\Models\User::where('role', $role->name)->count();
        if ($inUse > 0) {
            return response()->json([
                'ok'      => false,
                'message' => "Role '{$role->label}' masih digunakan oleh {$inUse} user. Pindahkan user terlebih dahulu.",
            ], 422);
        }

        $label = $role->label;
        $role->delete();

        $logger->write($request, 'ROLE_DELETE', 'roles', "Hapus role: {$role->name}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Role '{$label}' berhasil dihapus.",
        ]);
    }
}
