<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RKK = Rekap Kas Kecil per unit usaha. 1 baris = 1 baris jurnal detail
// (baris tipe "2" di file .RKK), field header (tanggal/keterangan/supplier)
// didenormalisasi ke tiap baris supaya tidak perlu join saat dianalisa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_rkk_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            $table->string('no_voucher', 60)->nullable();
            $table->string('no_urut', 30)->nullable();
            $table->string('kode_akun', 30)->nullable();
            $table->string('nama_akun', 190)->nullable();
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('nama_supplier', 190)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['unit_usaha_code', 'tanggal']);
            $table->index(['kode_akun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_rkk_transactions');
    }
};
