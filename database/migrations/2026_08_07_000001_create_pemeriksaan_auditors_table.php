<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama Auditor & Nama Auditee per (plan_audit_id, tool). Disimpan terpisah
     * dari tabel tiap tool pemeriksaan supaya tidak perlu migrasi ke-19 tabel
     * tool (sebagian banyak-baris-per-plan, sebagian satu-baris-JSON-per-plan,
     * dan Plafon sama sekali tidak punya tabel sendiri) dan tidak perlu
     * duplikasi nilai yang sama di tiap baris tool yang banyak-baris.
     */
    public function up(): void
    {
        Schema::create('pemeriksaan_auditors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('tool', 30);
            $table->string('nama_auditor', 150);
            $table->string('nama_auditee', 150);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['plan_audit_id', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_auditors');
    }
};
