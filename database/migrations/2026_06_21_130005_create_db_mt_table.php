<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_mt', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->nullable();
            $table->string('nama_singkat')->nullable();
            $table->text('nama_peralatan')->nullable();
            $table->string('kode_peralatan')->nullable();
            $table->string('jenis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_mt');
    }
};
