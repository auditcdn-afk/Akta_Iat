<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_grading', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('grade')->nullable();
            $table->decimal('nilai_min', 15, 2)->nullable();
            $table->decimal('nilai_max', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_grading');
    }
};
