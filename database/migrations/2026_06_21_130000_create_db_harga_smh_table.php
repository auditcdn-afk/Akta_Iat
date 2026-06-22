<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_harga_smh', function (Blueprint $table) {
            $table->id();
            $table->string('kode_model')->nullable();
            $table->string('nama_smh');
            $table->decimal('harga', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_harga_smh');
    }
};
