<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// LPK = Laporan Penjualan unit & penerimaan Kwitansi Gantung per unit usaha.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_lpk_penjualan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            $table->string('kode_urut', 30)->nullable();
            $table->string('kode_konsumen', 40)->nullable();
            $table->string('nama_konsumen', 190)->nullable();
            $table->string('kode_finance', 30)->nullable();
            $table->string('no_bukti', 40)->nullable();
            $table->string('no_faktur', 60)->nullable();
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('kode_transaksi', 20)->nullable(); // PBBO/PBAR/CRGT/CC
            $table->string('jenis_transaksi', 190)->nullable();
            $table->string('status_flag', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('raw_line');
            $table->timestamps();

            $table->index(['unit_usaha_code', 'tanggal']);
            $table->index(['kode_transaksi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_lpk_penjualan');
    }
};
