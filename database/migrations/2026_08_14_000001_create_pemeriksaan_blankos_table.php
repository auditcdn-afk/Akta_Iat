<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register Blanko yang Belum Digunakan (H1/H2) per (plan_audit_id, tool).
     * Disimpan terpisah dari tabel tiap tool pemeriksaan — pola yang sama
     * seperti pemeriksaan_auditors — supaya tidak perlu menambah kolom ke
     * tabel SMH (baris-per-unit) maupun BPKB Onhand (baris-per-BPKB) yang
     * bentuknya sama sekali tidak cocok untuk menyimpan data ini per baris.
     */
    public function up(): void
    {
        Schema::create('pemeriksaan_blankos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('tool', 30);
            $table->json('blanko_h1')->nullable();
            $table->json('blanko_h2')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['plan_audit_id', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_blankos');
    }
};
