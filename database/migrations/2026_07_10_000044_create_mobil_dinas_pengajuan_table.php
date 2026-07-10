<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobil_dinas_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->string('supir_request', 150);
            $table->date('tanggal_berangkat');
            $table->date('tanggal_pulang');
            $table->json('pic_mobil');
            $table->json('spd_file')->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->text('catatan_manajer')->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('nama_supir', 150)->nullable();
            $table->string('plat_mobil', 30)->nullable();
            $table->string('jenis_mobil', 100)->nullable();
            $table->string('completed_by', 100)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobil_dinas_pengajuan');
    }
};
