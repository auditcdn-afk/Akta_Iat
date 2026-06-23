<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // If the menus table exists and has a Task menu entry, restrict its roles
        // to admin, manajer, auditor only (hide from branch users).
        if (!Schema::hasTable('menus') || !Schema::hasTable('menu_role')) {
            return;
        }

        $menu = DB::table('menus')->where('route_name', 'akta.task')->first();
        if (!$menu) {
            return;
        }

        // Keep only admin, manajer, auditor in menu_role for this menu
        $allowed = ['admin', 'manajer', 'auditor'];

        // Get role IDs for allowed roles
        $roleIds = DB::table('roles')->whereIn('name', $allowed)->pluck('id');

        // Delete any menu_role entries for this menu that are NOT in allowed roles
        DB::table('menu_role')
            ->where('menu_id', $menu->id)
            ->whereNotIn('role_id', $roleIds)
            ->delete();

        // Ensure each allowed role has an entry
        foreach ($roleIds as $roleId) {
            $exists = DB::table('menu_role')
                ->where('menu_id', $menu->id)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                DB::table('menu_role')->insert([
                    'menu_id' => $menu->id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Restore all roles to the Task menu (revert to default)
        if (!Schema::hasTable('menus') || !Schema::hasTable('menu_role')) {
            return;
        }

        $menu = DB::table('menus')->where('route_name', 'akta.task')->first();
        if (!$menu) {
            return;
        }

        $allRoleIds = DB::table('roles')->pluck('id');
        foreach ($allRoleIds as $roleId) {
            $exists = DB::table('menu_role')
                ->where('menu_id', $menu->id)
                ->where('role_id', $roleId)
                ->exists();
            if (!$exists) {
                DB::table('menu_role')->insert([
                    'menu_id' => $menu->id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }
};
