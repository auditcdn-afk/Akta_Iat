<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pemeriksaan_bpkb_inproses', function (Blueprint $table) {
            $table->json('inproses_blocks_json')->nullable()->after('rincian_inproses_json');
        });
    }

    public function down(): void
    {
        Schema::table('pemeriksaan_bpkb_inproses', function (Blueprint $table) {
            $table->dropColumn('inproses_blocks_json');
        });
    }
};
