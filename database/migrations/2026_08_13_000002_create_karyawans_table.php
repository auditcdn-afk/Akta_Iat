<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data karyawan per unit usaha (nama, jabatan, foto). Dikelola bebas oleh
     * unit usaha yang bersangkutan (tambah/hapus kapan saja) dan ditampilkan
     * di halaman pertama Report Audit PDF untuk cabang tsb.
     */
    public function up(): void
    {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('unit_usaha', 150)->index();
            $table->string('nama', 150);
            $table->string('jabatan', 150);
            $table->string('photo_path')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};
