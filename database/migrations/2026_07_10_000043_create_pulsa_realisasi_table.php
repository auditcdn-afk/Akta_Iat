<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulsa_realisasi', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->nullable();
            $table->string('nama', 150);
            $table->string('jabatan', 100)->nullable();
            $table->date('tanggal');
            $table->string('nomor_hp', 30);
            $table->string('operator', 50)->nullable();
            $table->decimal('nominal', 14, 2)->default(0);
            $table->json('bon_file')->nullable();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('status', 20)->default('diajukan');
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['tahun', 'bulan']);
        });

        Schema::create('pulsa_periode', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('status', 20)->default('terbuka');
            $table->string('closed_by', 100)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulsa_periode');
        Schema::dropIfExists('pulsa_realisasi');
    }
};
