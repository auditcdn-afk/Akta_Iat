<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pemeriksaan_materai', function (Blueprint $table) {
            $table->integer('uang_10000')->nullable()->after('fisik');
        });
    }

    public function down(): void
    {
        Schema::table('pemeriksaan_materai', function (Blueprint $table) {
            $table->dropColumn('uang_10000');
        });
    }
};
