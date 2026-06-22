<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_perlengkapan', function (Blueprint $table) {
            $table->id();
            $table->string('tipe')->nullable();
            $table->string('nosin')->nullable();
            $table->text('aceh')->nullable();
            $table->text('riau')->nullable();
            $table->text('kepri')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_perlengkapan');
    }
};
