<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            // Drop old columns if they exist
            $cols = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM db_perlengkapan');
            $existing = array_column($cols, 'Field');

            if (in_array('kode', $existing))      $table->dropColumn('kode');
            if (in_array('nama', $existing))      $table->dropColumn('nama');
            if (in_array('satuan', $existing))    $table->dropColumn('satuan');
            if (in_array('qty', $existing))       $table->dropColumn('qty');
            if (in_array('keterangan', $existing)) $table->dropColumn('keterangan');
        });

        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $cols = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM db_perlengkapan');
            $existing = array_column($cols, 'Field');

            if (!in_array('tipe', $existing))  $table->string('tipe')->nullable()->after('id');
            if (!in_array('nosin', $existing)) $table->string('nosin')->nullable()->after('tipe');
            if (!in_array('aceh', $existing))  $table->text('aceh')->nullable()->after('nosin');
            if (!in_array('riau', $existing))  $table->text('riau')->nullable()->after('aceh');
            if (!in_array('kepri', $existing)) $table->text('kepri')->nullable()->after('riau');
            if (!in_array('type', $existing))  $table->string('type')->nullable()->after('kepri');
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
