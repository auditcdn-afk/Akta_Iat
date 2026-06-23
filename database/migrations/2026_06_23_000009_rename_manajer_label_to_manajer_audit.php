<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        // Ubah label tampilan role manajer menjadi "Manajer Audit".
        // Slug (name) tetap 'manajer' agar middleware & birokrasi tidak rusak.
        DB::table('roles')
            ->where('name', 'manajer')
            ->update(['label' => 'Manajer Audit']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('name', 'manajer')
            ->update(['label' => 'Manajer']);
    }
};
