<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_acc_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            $table->string('no_bukti', 40)->nullable();
            $table->string('no_faktur', 60)->nullable();
            $table->string('kode_konsumen', 40)->nullable();
            $table->string('jenis', 30)->nullable(); // REG, dsb
            $table->decimal('harga_otr', 18, 2)->default(0);
            $table->decimal('dp', 18, 2)->default(0);
            $table->decimal('bunga', 8, 3)->nullable();
            $table->string('kode_sales', 30)->nullable();
            $table->string('status_flag', 20)->nullable(); // P/L/dst
            $table->string('status_kredit', 30)->nullable(); // LANCAR/dst
            $table->string('cara_bayar', 20)->nullable(); // CASH/dst
            $table->text('raw_line');
            $table->timestamps();

            $table->index(['unit_usaha_code', 'tanggal']);
            $table->index(['kode_konsumen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_acc_contracts');
    }
};
