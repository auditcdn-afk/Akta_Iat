<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10); // rkk | acc | lpk
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            // Hash baris pertama file (dari sistem sumbernya) — dipakai untuk
            // menolak proses ulang kalau file yang sama diupload dua kali.
            $table->string('source_hash', 64);
            $table->string('source_filename', 190);
            $table->unsignedInteger('row_count')->default(0);
            $table->string('uploaded_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['jenis', 'source_hash']);
            $table->index(['jenis', 'unit_usaha_code', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_uploads');
    }
};
