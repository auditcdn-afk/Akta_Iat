<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_unit_usaha', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_unit_usaha');
    }
};
