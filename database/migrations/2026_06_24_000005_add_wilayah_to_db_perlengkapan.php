<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $table->string('wilayah', 50)->nullable()->after('kode');
        });
    }

    public function down(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $table->dropColumn('wilayah');
        });
    }
};
