<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_dinas_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realisasi_dinas_id')->constrained('realisasi_dinas')->cascadeOnDelete();
            $table->string('jenis_pengeluaran', 60);
            $table->text('catatan')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['realisasi_dinas_id']);
            $table->index(['jenis_pengeluaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_dinas_items');
    }
};
