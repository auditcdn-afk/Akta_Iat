<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')->updateOrInsert(
                ['name' => 'mrr'],
                [
                    'label' => 'MRR',
                    'color' => 'indigo',
                    'description' => 'Menerima form pengajuan mobil dinas dan melengkapi data supir/kendaraan.',
                    'is_system' => false,
                    'order' => 11,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('users') && !DB::table('users')->where('username', 'mrr')->exists()) {
            DB::table('users')->insert([
                'username' => 'mrr',
                'name' => 'MRR',
                'display_name' => 'MRR',
                'email' => 'mrr@akta-iat.local',
                'password' => Hash::make('mrr12345'),
                'plain_password' => 'mrr12345',
                'role' => 'mrr',
                'unit_usaha' => null,
                'is_disabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('menus') && Schema::hasTable('menu_roles')) {
            $menu = DB::table('menus')->where('route_name', 'akta.mobil-dinas')->first();
            if ($menu) {
                $allowed = ['admin', 'manajer', 'auditor', 'mrr'];
                $existing = DB::table('menu_roles')->where('menu_id', $menu->id)->pluck('role')->toArray();
                foreach ($allowed as $role) {
                    if (!in_array($role, $existing, true)) {
                        DB::table('menu_roles')->insert([
                            'menu_id' => $menu->id,
                            'role' => $role,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where('username', 'mrr')->delete();
        }
        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'mrr')->delete();
        }
    }
};
