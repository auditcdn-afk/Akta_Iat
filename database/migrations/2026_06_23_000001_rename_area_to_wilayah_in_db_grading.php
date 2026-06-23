<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_grading', function (Blueprint $table) {
            if (Schema::hasColumn('db_grading', 'area') && ! Schema::hasColumn('db_grading', 'wilayah')) {
                $table->renameColumn('area', 'wilayah');
            }
        });
    }

    public function down(): void
    {
        Schema::table('db_grading', function (Blueprint $table) {
            if (Schema::hasColumn('db_grading', 'wilayah') && ! Schema::hasColumn('db_grading', 'area')) {
                $table->renameColumn('wilayah', 'area');
            }
        });
    }
};
