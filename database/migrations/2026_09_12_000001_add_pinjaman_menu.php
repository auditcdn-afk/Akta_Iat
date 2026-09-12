<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu utama "Pinjaman BPK & BPB".
 *
 * Menu produksi dibaca dari tabel `menus` (config/akta_menu.php hanya cadangan
 * saat tabelnya masih kosong — lihat AktaMenuService::hasDbMenus), jadi entri
 * baru di config saja TIDAK akan pernah muncul di server. Dimasukkan lewat
 * migration supaya ikut terpasang saat /deploy/migrate dipanggil.
 *
 * Kewenangan lihatnya dua tingkat, dan yang di bawah ini hanya menentukan siapa
 * yang melihat MENU-nya. Isi daftarnya sendiri disaring di
 * PinjamanCabangController::daftar: koordinator, unit, dan bpk hanya menerima
 * pengajuan yang menjadi birokrasinya sendiri.
 */
return new class extends Migration
{
    private const ROUTE = 'akta.pinjaman';

    private const ROLES = ['admin', 'manajer', 'auditor', 'coo', 'koordinator', 'unit', 'bpk'];

    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_roles')) {
            return;
        }

        $menu = DB::table('menus')->where('route_name', self::ROUTE)->first();

        if ($menu) {
            $menuId = $menu->id;
        } else {
            // Diletakkan tepat setelah SK supaya berdekatan dengan menu
            // operasional lain, bukan terlempar ke paling bawah di antara
            // menu administrasi sistem.
            $urutanSk = (int) (DB::table('menus')->where('route_name', 'akta.sk')->value('order')
                ?? DB::table('menus')->max('order'));

            DB::table('menus')->where('order', '>', $urutanSk)->increment('order');

            $menuId = DB::table('menus')->insertGetId([
                'label'      => 'Pinjaman BPK & BPB',
                'code'       => 'PB',
                'route_name' => self::ROUTE,
                'path'       => '/akta/pinjaman',
                'icon'       => 'circle',
                'order'      => $urutanSk + 1,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sudahAda = DB::table('menu_roles')->where('menu_id', $menuId)->pluck('role')->all();

        foreach (self::ROLES as $role) {
            if (! in_array($role, $sudahAda, true)) {
                DB::table('menu_roles')->insert([
                    'menu_id'    => $menuId,
                    'role'       => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $menu = DB::table('menus')->where('route_name', self::ROUTE)->first();

        if ($menu) {
            DB::table('menu_roles')->where('menu_id', $menu->id)->delete();
            DB::table('menus')->where('id', $menu->id)->delete();
        }
    }
};
