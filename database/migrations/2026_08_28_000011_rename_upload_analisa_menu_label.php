<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('route_name', 'akta.upload-analisa')
            ->update(['label' => 'Upload Data', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('route_name', 'akta.upload-analisa')
            ->update(['label' => 'Upload Data Analisa', 'updated_at' => now()]);
    }
};
