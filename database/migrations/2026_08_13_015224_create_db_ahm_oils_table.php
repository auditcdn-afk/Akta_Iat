<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data kode part oli AHM ("AHM Oil's"). Dipakai untuk membedakan
     * item HGP/RSA HGP yang selisihnya masuk rekap "AHM OIL'S" (kode part-nya
     * terdaftar di sini) dari yang masuk rekap "SPAREPART" (tidak terdaftar).
     */
    public function up(): void
    {
        Schema::create('db_ahm_oils', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_ahm_oils');
    }
};
