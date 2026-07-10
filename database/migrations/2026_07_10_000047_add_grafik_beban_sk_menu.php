<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('menu_roles')) {
            return;
        }

        $maxOrder = (int) DB::table('menus')->max('order');

        $menu = DB::table('menus')->where('route_name', 'akta.grafik-beban-sk')->first();
        if (!$menu) {
            $menuId = DB::table('menus')->insertGetId([
                'label' => 'Grafik Beban SK',
                'code' => 'GB',
                'route_name' => 'akta.grafik-beban-sk',
                'path' => '/akta/grafik-beban-sk',
                'icon' => 'circle',
                'order' => $maxOrder + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $menuId = $menu->id;
        }

        $allowed = ['admin', 'manajer', 'auditor', 'koordinator', 'coo'];
        $existing = DB::table('menu_roles')->where('menu_id', $menuId)->pluck('role')->toArray();
        foreach ($allowed as $role) {
            if (!in_array($role, $existing, true)) {
                DB::table('menu_roles')->insert([
                    'menu_id' => $menuId,
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }
        $menu = DB::table('menus')->where('route_name', 'akta.grafik-beban-sk')->first();
        if ($menu) {
            DB::table('menu_roles')->where('menu_id', $menu->id)->delete();
            DB::table('menus')->where('id', $menu->id)->delete();
        }
    }
};
