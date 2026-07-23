<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_dinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->date('tanggal_settlement');
            $table->json('personil');
            $table->string('jenis_pengeluaran', 60);
            $table->text('catatan')->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->json('bukti_file')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['plan_audit_id']);
            $table->index(['tanggal_settlement']);
            $table->index(['jenis_pengeluaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_dinas');
    }
};
