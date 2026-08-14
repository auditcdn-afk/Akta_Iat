<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SQLite (unlike MySQL/InnoDB) does not auto-index foreignId() columns,
        // so every checkItem() save ran two full-table COUNT() scans on this
        // FK — the more audit cycles piled up, the slower each save got.
        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->index('pemeriksaan_smh_id');
        });
    }

    public function down(): void
    {
        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->dropIndex(['pemeriksaan_smh_id']);
        });
    }
};
