<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $cols = array_column(DB::select('SHOW COLUMNS FROM db_perlengkapan'), 'Field');

        // Drop legacy regional columns if present
        $legacyDrop = array_filter(['tipe', 'nosin', 'aceh', 'riau', 'kepri', 'type'], fn($c) => in_array($c, $cols));
        if ($legacyDrop) {
            Schema::table('db_perlengkapan', fn(Blueprint $t) => $t->dropColumn(array_values($legacyDrop)));
        }

        // Re-read after drop
        $cols = array_column(DB::select('SHOW COLUMNS FROM db_perlengkapan'), 'Field');

        Schema::table('db_perlengkapan', function (Blueprint $t) use ($cols) {
            if (!in_array('kode', $cols))       $t->string('kode', 20)->nullable()->after('id');
            if (!in_array('nama', $cols))       $t->string('nama')->nullable()->after('kode');
            if (!in_array('satuan', $cols))     $t->string('satuan', 50)->nullable()->after('nama');
            if (!in_array('qty', $cols))        $t->decimal('qty', 15, 2)->nullable()->after('satuan');
            if (!in_array('keterangan', $cols)) $t->text('keterangan')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $t) {
            $cols = array_column(DB::select('SHOW COLUMNS FROM db_perlengkapan'), 'Field');
            $newDrop = array_filter(['kode', 'nama', 'satuan', 'qty', 'keterangan'], fn($c) => in_array($c, $cols));
            if ($newDrop) $t->dropColumn(array_values($newDrop));

            if (!in_array('tipe', $cols))  $t->string('tipe')->nullable();
            if (!in_array('nosin', $cols)) $t->string('nosin')->nullable();
            if (!in_array('aceh', $cols))  $t->text('aceh')->nullable();
            if (!in_array('riau', $cols))  $t->text('riau')->nullable();
            if (!in_array('kepri', $cols)) $t->text('kepri')->nullable();
            if (!in_array('type', $cols))  $t->string('type')->nullable();
        });
    }
};
