<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_perlengkapan', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('satuan')->nullable();
            $table->decimal('qty', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_perlengkapan');
    }
};
