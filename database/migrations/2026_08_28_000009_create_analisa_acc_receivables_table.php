<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Baris tipe "F" di file .ACC — daftar piutang/tagihan konsumen yang
// BELUM lunas per tanggal laporan (jauh lebih banyak barisnya daripada
// tipe "0"/"1" — di sample nyata bisa ~85% dari isi file). Ini indikator
// paling relevan untuk skor zona: makin banyak & makin lama piutang
// menumpuk di satu unit usaha, makin perlu sering dikunjungi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_acc_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal_laporan');
            $table->string('kode_konsumen', 40)->nullable();
            $table->string('no_bukti', 40)->nullable();
            $table->date('tanggal_transaksi')->nullable();
            $table->string('kode_sales', 30)->nullable();
            $table->decimal('nominal', 18, 2)->default(0);
            $table->text('raw_line');
            $table->timestamps();

            $table->index(['unit_usaha_code', 'tanggal_laporan']);
            $table->index(['kode_konsumen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_acc_receivables');
    }
};
