<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sk_distribusi', function (Blueprint $table) {
            $table->json('tanggapan_poin')->nullable()->after('tanggapan');
        });
    }

    public function down(): void
    {
        Schema::table('sk_distribusi', function (Blueprint $table) {
            $table->dropColumn('tanggapan_poin');
        });
    }
};
