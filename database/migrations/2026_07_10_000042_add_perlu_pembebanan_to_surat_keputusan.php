<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keputusan', function (Blueprint $table) {
            $table->boolean('perlu_pembebanan')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('surat_keputusan', function (Blueprint $table) {
            $table->dropColumn('perlu_pembebanan');
        });
    }
};
