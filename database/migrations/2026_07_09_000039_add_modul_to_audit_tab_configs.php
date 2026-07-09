<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_tab_configs', function (Blueprint $table) {
            $table->string('modul', 30)->default('audit')->after('id');
        });

        Schema::table('audit_tab_configs', function (Blueprint $table) {
            $table->dropUnique(['jenis_audit', 'tab_key']);
            $table->unique(['modul', 'jenis_audit', 'tab_key']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_tab_configs', function (Blueprint $table) {
            $table->dropUnique(['modul', 'jenis_audit', 'tab_key']);
            $table->unique(['jenis_audit', 'tab_key']);
            $table->dropColumn('modul');
        });
    }
};
