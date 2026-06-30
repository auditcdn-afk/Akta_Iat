<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bu_performances')) {
            Schema::create('bu_performances', function (Blueprint $table) {
                $table->id();
                $table->string('bulan');          // e.g. "Januari 2026"
                $table->string('unit_usaha');
                $table->string('auditor')->nullable();
                $table->json('penilaian')->nullable(); // [{pic, jabatan, uraian}]
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bu_performances');
    }
};
