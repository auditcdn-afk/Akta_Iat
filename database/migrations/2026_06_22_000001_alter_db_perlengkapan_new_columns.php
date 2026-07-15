<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            foreach (['kode', 'nama', 'satuan', 'qty', 'keterangan'] as $col) {
                if (Schema::hasColumn('db_perlengkapan', $col)) $table->dropColumn($col);
            }
        });

        Schema::table('db_perlengkapan', function (Blueprint $table) {
            if (!Schema::hasColumn('db_perlengkapan', 'tipe'))  $table->string('tipe')->nullable()->after('id');
            if (!Schema::hasColumn('db_perlengkapan', 'nosin')) $table->string('nosin')->nullable()->after('tipe');
            if (!Schema::hasColumn('db_perlengkapan', 'aceh'))  $table->text('aceh')->nullable()->after('nosin');
            if (!Schema::hasColumn('db_perlengkapan', 'riau'))  $table->text('riau')->nullable()->after('aceh');
            if (!Schema::hasColumn('db_perlengkapan', 'kepri')) $table->text('kepri')->nullable()->after('riau');
            if (!Schema::hasColumn('db_perlengkapan', 'type'))  $table->string('type')->nullable()->after('kepri');
        });
    }

    public function down(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $table->dropColumn(['tipe', 'nosin', 'aceh', 'riau', 'kepri', 'type']);
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('satuan')->nullable();
            $table->decimal('qty', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
        });
    }
};
