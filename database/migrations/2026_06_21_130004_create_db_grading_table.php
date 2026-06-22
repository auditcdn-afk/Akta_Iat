<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_grading', function (Blueprint $table) {
            $table->id();
            $table->string('id_grading')->nullable();
            $table->string('jenis')->nullable();
            $table->string('area')->nullable();
            $table->text('nama_pemeriksaan')->nullable();
            $table->text('hasil_pemeriksaan')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('bknf')->nullable();
            $table->decimal('pknf', 10, 4)->nullable();
            $table->string('bkf')->nullable();
            $table->decimal('pkf', 10, 4)->nullable();
            $table->string('bnknf')->nullable();
            $table->decimal('pnknf', 10, 4)->nullable();
            $table->string('bnkf')->nullable();
            $table->decimal('pnkf', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_grading');
    }
};
