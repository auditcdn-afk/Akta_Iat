<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field tambahan untuk menyesuaikan user AKTA IAT.
     * Breeze tetap memakai kolom bawaan: name, email, password.
     * API AKTA IAT memakai username, display_name, role, unit_usaha, dan status disabled.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name', 200)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->default('auditor')->after('password');
            }

            if (! Schema::hasColumn('users', 'unit_usaha')) {
                $table->string('unit_usaha', 100)->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('unit_usaha');
            }

            if (! Schema::hasColumn('users', 'created_by')) {
                $table->string('created_by', 100)->nullable()->after('is_disabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('users', 'is_disabled')) {
                $table->dropColumn('is_disabled');
            }

            if (Schema::hasColumn('users', 'unit_usaha')) {
                $table->dropColumn('unit_usaha');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }

            if (Schema::hasColumn('users', 'display_name')) {
                $table->dropColumn('display_name');
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }
        });
    }
};
