<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->date('tgl_periksa')->nullable()->after('checked_at');
            $table->string('keterangan_kondisi')->nullable()->after('tgl_periksa'); // ready_for_sale, rusak, dll
            $table->json('perlengkapan_json')->nullable()->after('keterangan_kondisi'); // [{nama, ada: true/false}]
        });
    }

    public function down(): void
    {
        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->dropColumn(['tgl_periksa', 'keterangan_kondisi', 'perlengkapan_json']);
        });
    }
};
