<?php

namespace App\Services;

use App\Models\Menu;

/**
 * AktaMenuService — sumber kebenaran menu sidebar.
 *
 * Urutan prioritas:
 *  1. Tabel `menus` di database (dinamis, bisa dikelola admin).
 *  2. Config `akta_menu.items` (fallback jika tabel masih kosong).
 *
 * Keamanan: filter berdasarkan role hanya sebagai UX (menyembunyikan menu).
 * Proteksi sesungguhnya ada di layer API middleware (`akta.role:*`).
 */
class AktaMenuService
{
    // Daftar role yang dikenal aplikasi
    public const ROLES = ['admin', 'manajer', 'auditor', 'viewer'];

    // ── Public API ────────────────────────────────────────────────

    /**
     * Semua item menu aktif untuk sidebar PHP.
     * Menyertakan field `roles` agar JS bisa filter client-side.
     */
    public function visibleItems(): array
    {
        if (!$this->hasDbMenus()) {
            return $this->configItemsWithDefaultRoles();
        }

        return Menu::active()
            ->rootLevel()
            ->orderBy('order')
            ->get()
            ->map(fn(Menu $m) => $this->toSidebarArray($m))
            ->toArray();
    }

    /**
     * Menu yang boleh diakses role tertentu — untuk API endpoint.
     * Server memfilter; client hanya menerima apa yang diizinkan.
     */
    public function itemsForRole(string $role): array
    {
        if (!$this->hasDbMenus()) {
            return $this->configItemsForRole($role);
        }

        return Menu::active()
            ->rootLevel()
            ->forRole($role)
            ->orderBy('order')
            ->get()
            ->map(fn(Menu $m) => [
                'label'     => $m->label,
                'code'      => $m->code,
                'routeName' => $m->route_name,
                'path'      => $m->path,
                'icon'      => $m->icon,
            ])
            ->toArray();
    }

    /**
     * Semua menu (termasuk tidak aktif) beserta info roles — untuk halaman admin.
     */
    public function allItemsWithRoles(): array
    {
        return Menu::rootLevel()
            ->orderBy('order')
            ->get()
            ->map(fn(Menu $m) => $m->toAktaArray())
            ->toArray();
    }

    /**
     * Seed tabel menus dari config (dipanggil satu kali oleh admin atau seeder).
     */
    public function seedFromConfig(?string $seededBy = null): int
    {
        $defaults = config('akta_menu.items', []);
        $count    = 0;

        foreach ($defaults as $index => $item) {
            $menu = Menu::firstOrCreate(
                ['route_name' => $item['route']],
                [
                    'label'    => $item['label'],
                    'code'     => $item['code'],
                    'path'     => $item['path'],
                    'icon'     => 'circle',
                    'order'    => $index + 1,
                    'is_active'=> true,
                ]
            );

            // Default roles berdasarkan flag admin_only dari config lama
            $roles = ($item['admin_only'] ?? false)
                ? ['admin']
                : self::ROLES;

            $menu->syncRoles($roles);
            $count++;
        }

        return $count;
    }

    // ── Backward-compat (dipanggil sidebar blade lama) ────────────

    /** @deprecated Gunakan visibleItems() */
    public function items(): array
    {
        return $this->visibleItems();
    }

    // ── Private helpers ───────────────────────────────────────────

    private function hasDbMenus(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Menu::exists();
        }
        return $cached;
    }

    private function toSidebarArray(Menu $m): array
    {
        return [
            'label'      => $m->label,
            'route'      => $m->route_name,
            'path'       => $m->path,
            'code'       => $m->code,
            'icon'       => $m->icon,
            'is_active'  => $m->is_active,
            // Roles digunakan JS client-side untuk filter tampilan
            'roles'      => $m->getAllowedRoles(),
            // Backward-compat: admin_only true jika hanya role admin yang boleh
            'admin_only' => $m->getAllowedRoles() === ['admin'],
        ];
    }

    private function configItemsWithDefaultRoles(): array
    {
        return collect(config('akta_menu.items', []))
            ->map(fn($item) => array_merge($item, [
                'roles'      => ($item['admin_only'] ?? false) ? ['admin'] : self::ROLES,
                'is_active'  => true,
                'route'      => $item['route'],
            ]))
            ->toArray();
    }

    private function configItemsForRole(string $role): array
    {
        return collect(config('akta_menu.items', []))
            ->filter(fn($item) => $role === 'admin' || !($item['admin_only'] ?? false))
            ->values()
            ->toArray();
    }
}
