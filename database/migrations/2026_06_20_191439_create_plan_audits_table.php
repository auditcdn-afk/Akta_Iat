<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel Plan Audit AKTA IAT.
     * Ini menjadi induk untuk task, rekomendasi, SK, report, dan pemeriksaan.
     */
    public function up(): void
    {
        Schema::create('plan_audits', function (Blueprint $table) {
            $table->id();
            $table->string('no_spt', 100)->nullable()->index();
            $table->string('cabang', 150)->nullable()->index();
            $table->string('cabang_area', 150)->nullable();
            $table->string('jenis_audit', 100)->nullable()->index();
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->string('kepala_tim', 150)->nullable();
            $table->json('tim')->nullable();
            $table->string('status', 50)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index(['tgl_mulai', 'tgl_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_audits');
    }
};
