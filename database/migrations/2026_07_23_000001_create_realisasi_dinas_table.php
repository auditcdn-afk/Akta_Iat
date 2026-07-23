<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Realisasi Dinas: satu record "header" per plan audit (dikunci per plan —
// tidak bisa dipindah ke plan lain). Rincian jenis pengeluaran ada di tabel
// terpisah realisasi_dinas_items (lihat migration 000003) supaya satu plan
// bisa punya banyak baris pengeluaran, tapi cuma satu personil/bukti/status.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_dinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->unique()->constrained('plan_audits')->cascadeOnDelete();
            $table->json('personil');
            $table->json('bukti_file')->nullable();
            $table->string('status', 10)->default('draft'); // draft | selesai
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by', 100)->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_dinas');
    }
};
