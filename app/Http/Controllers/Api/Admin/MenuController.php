<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\Menu;
use App\Services\ActivityLogger;
use App\Services\AktaMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    // ── Read ─────────────────────────────────────────────────────

    /** GET /api/admin/menus — semua menu + info roles (untuk halaman admin). */
    public function index(AktaMenuService $svc): JsonResponse
    {
        return response()->json([
            'ok'    => true,
            'data'  => $svc->allItemsWithRoles(),
            'roles' => AktaMenuService::activeRoles(),
        ]);
    }

    /** GET /api/menus — menu yang boleh diakses user yang login (server-filtered). */
    public function myMenus(Request $request, AktaMenuService $svc): JsonResponse
    {
        $role = $request->user()->role ?? 'viewer';

        return response()->json([
            'ok'   => true,
            'data' => $svc->itemsForRole($role),
        ]);
    }

    // ── Write ─────────────────────────────────────────────────────

    /** POST /api/admin/menus — tambah menu baru. */
    public function store(MenuRequest $request, ActivityLogger $logger): JsonResponse
    {
        $menu = DB::transaction(function () use ($request) {
            $menu = Menu::create($request->only([
                'label', 'code', 'route_name', 'path', 'icon', 'parent_id', 'order', 'is_active',
            ]));
            $menu->syncRoles($request->input('roles', []));
            return $menu;
        });

        $logger->write($request, 'MENU_CREATE', 'menu', "Tambah menu: {$menu->label}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Menu berhasil ditambahkan.',
            'data'    => $menu->toAktaArray(),
        ], 201);
    }

    /** PUT /api/admin/menus/{menu} — update menu & role assignment. */
    public function update(MenuRequest $request, Menu $menu, ActivityLogger $logger): JsonResponse
    {
        DB::transaction(function () use ($request, $menu) {
            $menu->update($request->only([
                'label', 'code', 'route_name', 'path', 'icon', 'parent_id', 'order', 'is_active',
            ]));
            $menu->syncRoles($request->input('roles', []));
        });

        $logger->write($request, 'MENU_UPDATE', 'menu', "Update menu: {$menu->label}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Menu berhasil diperbarui.',
            'data'    => $menu->fresh()->toAktaArray(),
        ]);
    }

    /** DELETE /api/admin/menus/{menu} — hapus menu. */
    public function destroy(Request $request, Menu $menu, ActivityLogger $logger): JsonResponse
    {
        // Security: cegah hapus menu Dashboard (fallback root)
        if ($menu->route_name === 'akta.dashboard') {
            return response()->json([
                'ok'      => false,
                'message' => 'Menu Dashboard tidak dapat dihapus.',
            ], 422);
        }

        $label = $menu->label;
        $menu->delete(); // cascade hapus menu_roles via DB constraint

        $logger->write($request, 'MENU_DELETE', 'menu', "Hapus menu: {$label}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Menu '{$label}' berhasil dihapus.",
        ]);
    }

    /** PUT /api/admin/menus/{menu}/roles — update hanya roles (tanpa ubah data menu). */
    public function updateRoles(Request $request, Menu $menu, ActivityLogger $logger): JsonResponse
    {
        // Security: hanya admin
        if (!$request->user()?->isAdmin()) {
            return response()->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(AktaMenuService::ROLES)],
        ]);

        $menu->syncRoles($validated['roles']);

        $logger->write(
            $request, 'MENU_ROLES_UPDATE', 'menu',
            "Update roles menu '{$menu->label}': " . implode(', ', $validated['roles']),
            $request->user()
        );

        return response()->json([
            'ok'      => true,
            'message' => 'Role menu berhasil diperbarui.',
            'data'    => $menu->fresh()->toAktaArray(),
        ]);
    }

    /** PUT /api/admin/menus/{menu}/toggle — aktifkan/nonaktifkan menu. */
    public function toggle(Request $request, Menu $menu, ActivityLogger $logger): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $menu->update(['is_active' => !$menu->is_active]);
        $status = $menu->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $logger->write($request, 'MENU_TOGGLE', 'menu', "Menu '{$menu->label}' {$status}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Menu '{$menu->label}' berhasil {$status}.",
            'data'    => $menu->toAktaArray(),
        ]);
    }

    /**
     * POST /api/admin/menus/seed — populate tabel menus dari config.
     * Aman untuk dijalankan berkali-kali (idempotent via firstOrCreate).
     */
    public function seed(Request $request, AktaMenuService $svc, ActivityLogger $logger): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $count = $svc->seedFromConfig($request->user()?->username);

        $logger->write($request, 'MENU_SEED', 'menu', "Seed {$count} menu dari config", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "{$count} menu berhasil diseed dari konfigurasi.",
            'data'    => $svc->allItemsWithRoles(),
        ]);
    }

    /** POST /api/admin/menus/reorder — ubah urutan menu sekaligus. */
    public function reorder(Request $request, ActivityLogger $logger): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return response()->json(['ok' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'items'       => ['required', 'array'],
            'items.*.id'  => ['required', 'integer', 'exists:menus,id'],
            'items.*.order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Menu::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        $logger->write($request, 'MENU_REORDER', 'menu', 'Reorder menu sidebar', $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Urutan menu berhasil disimpan.',
        ]);
    }
}
