<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'analisa_zona_access')) {
            DB::table('users')->where('username', 'aziz1')->update(['analisa_zona_access' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'analisa_zona_access')) {
            DB::table('users')->where('username', 'aziz1')->update(['analisa_zona_access' => false]);
        }
    }
};
