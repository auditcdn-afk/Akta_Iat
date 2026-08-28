<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menu "Upload Data Analisa" sengaja TIDAK diberi baris di menu_roles sama
// sekali — pola yang sudah ada di sistem menu dinamis ini menganggap menu
// tanpa role terdaftar sebagai "terbuka untuk semua role" (lihat
// AktaMenuService::toSidebarArray() dan filter role di sidebar.blade.php).
// Ini beda dengan menu "Analisa Zona" (dashboard skor + data pribadi
// konsumen) yang gatingnya lewat flag users.analisa_zona_access, bukan lewat
// menu ini.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $maxOrder = (int) DB::table('menus')->max('order');

        $exists = DB::table('menus')->where('route_name', 'akta.upload-analisa')->exists();
        if (!$exists) {
            DB::table('menus')->insert([
                'label' => 'Upload Data Analisa',
                'code' => 'UA',
                'route_name' => 'akta.upload-analisa',
                'path' => '/akta/upload-analisa',
                'icon' => 'circle',
                'order' => $maxOrder + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }
        $menu = DB::table('menus')->where('route_name', 'akta.upload-analisa')->first();
        if ($menu) {
            DB::table('menu_roles')->where('menu_id', $menu->id)->delete();
            DB::table('menus')->where('id', $menu->id)->delete();
        }
    }
};
