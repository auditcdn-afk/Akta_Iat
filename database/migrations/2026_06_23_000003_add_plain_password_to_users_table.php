<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Salinan password dalam bentuk teks agar admin bisa melihatnya.
            // Hanya diisi saat password diketahui (dibuat/di-reset oleh admin / generator).
            if (! Schema::hasColumn('users', 'plain_password')) {
                $table->string('plain_password', 100)->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'plain_password')) {
                $table->dropColumn('plain_password');
            }
        });
    }
};
