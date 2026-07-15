<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_mt', function (Blueprint $table) {
            $existing = Schema::getColumnListing('db_mt');
            foreach (['kode', 'nama', 'periode', 'keterangan'] as $col) {
                if (in_array($col, $existing)) $table->dropColumn($col);
            }
        });

        Schema::table('db_mt', function (Blueprint $table) {
            $existing = Schema::getColumnListing('db_mt');
            if (!in_array('nomor', $existing))          $table->string('nomor')->nullable()->after('id');
            if (!in_array('nama_singkat', $existing))   $table->string('nama_singkat')->nullable()->after('nomor');
            if (!in_array('nama_peralatan', $existing)) $table->text('nama_peralatan')->nullable()->after('nama_singkat');
            if (!in_array('kode_peralatan', $existing)) $table->string('kode_peralatan')->nullable()->after('nama_peralatan');
            // 'jenis' column already exists
        });
    }

    public function down(): void
    {
        Schema::table('db_mt', function (Blueprint $table) {
            $table->dropColumn(['nomor', 'nama_singkat', 'nama_peralatan', 'kode_peralatan']);
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('periode')->nullable();
            $table->text('keterangan')->nullable();
        });
    }
};
