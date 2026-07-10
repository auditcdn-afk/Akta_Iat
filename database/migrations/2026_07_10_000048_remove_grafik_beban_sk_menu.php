<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Grafik Beban SK digabung ke dalam menu Dashboard (sebagai tab), jadi menu
// sidebar terpisah yang sempat dibuat di migration 2026_07_10_000047 dihapus.
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        // Tidak perlu dikembalikan; menu ini sengaja digabung ke Dashboard.
    }
};
