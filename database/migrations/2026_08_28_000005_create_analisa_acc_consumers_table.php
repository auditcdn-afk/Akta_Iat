<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ACC = data master konsumen (kredit/pembiayaan unit) per unit usaha.
// Berisi data pribadi (NIK, HP, alamat) — lihat catatan retensi &
// pembatasan akses di AnalisaZonaController.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_acc_consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            $table->string('kode_konsumen', 40)->nullable();
            $table->string('nama', 190)->nullable();
            $table->string('no_hp', 30)->nullable();
            $table->string('nik', 30)->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->string('no_rangka', 40)->nullable();
            $table->string('dusun', 190)->nullable();
            $table->string('kecamatan', 120)->nullable();
            $table->string('kabupaten', 120)->nullable();
            $table->string('desa', 190)->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('kode_wilayah', 30)->nullable();
            // Baris mentah asli — field ACC belum sepenuhnya terverifikasi
            // maknanya satu-satu, disimpan supaya bisa diparse ulang tanpa
            // perlu minta upload ulang kalau ada koreksi mapping kolom.
            $table->text('raw_line');
            $table->timestamps();

            $table->index(['unit_usaha_code', 'tanggal']);
            $table->index(['kode_konsumen']);
            $table->index(['nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_acc_consumers');
    }
};
