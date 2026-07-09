<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keputusan', function (Blueprint $table) {
            $table->text('memutuskan')->nullable()->after('file_sk');
        });
    }

    public function down(): void
    {
        Schema::table('surat_keputusan', function (Blueprint $table) {
            $table->dropColumn('memutuskan');
        });
    }
};
