<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP karyawan unit usaha.
 *
 * Nullable: data karyawan yang sudah terlanjur diinput tidak punya nomor, dan
 * memaksanya terisi akan membuat seluruh baris lama tidak valid. Disimpan apa
 * adanya sebagai teks — bukan angka — karena nomor Indonesia diawali "0" dan
 * sering ditulis dengan "+62", spasi, atau tanda hubung.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('karyawans') || Schema::hasColumn('karyawans', 'no_hp')) {
            return;
        }

        Schema::table('karyawans', function (Blueprint $table) {
            $table->string('no_hp', 30)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('karyawans') && Schema::hasColumn('karyawans', 'no_hp')) {
            Schema::table('karyawans', function (Blueprint $table) {
                $table->dropColumn('no_hp');
            });
        }
    }
};
