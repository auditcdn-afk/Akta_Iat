<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur Analisa Zona (RKK/ACC/LPK dari unit usaha) berisi data pribadi
 * konsumen (NIK, alamat, no. HP) — sengaja TIDAK memakai kolom `role` yang
 * sudah ada (satu user cuma boleh punya satu role, dan mengubahnya akan
 * menghapus akses role auditor/admin yang sedang dipakai). Sebagai gantinya,
 * ini flag terpisah yang bisa dinyalakan admin per-user dari halaman Kelola
 * User tanpa mengubah role utama mereka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('analisa_zona_access')->default(false)->after('is_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('analisa_zona_access');
        });
    }
};
