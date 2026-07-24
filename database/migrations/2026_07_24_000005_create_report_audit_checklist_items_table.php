<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Detail item pemeriksaan grading dalam format "panjang" (satu baris per item),
// hasil unnest dari audit_gradings.details (JSON). Dipilih format panjang
// (bukan 90+ kolom lebar seperti file Excel lama) supaya tidak rapuh terhadap
// perubahan daftar item pemeriksaan master (db_grading) — Power BI bisa
// mem-pivot sendiri, dan fitur export Excel di aplikasi mem-pivotnya balik
// on-demand ke bentuk lebar saat dibutuhkan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->foreignId('audit_grading_id')->nullable()->constrained('audit_gradings')->nullOnDelete();

            $table->string('jenis', 50)->nullable()->index(); // Cabang, Bengkel, WHS UNIT, WHS PART
            $table->unsignedInteger('urutan')->default(0);
            $table->text('nama_pemeriksaan')->nullable();
            $table->text('current_condition')->nullable();
            $table->decimal('nilai', 10, 4)->nullable();

            $table->timestamp('refreshed_at')->nullable();

            $table->index(['plan_audit_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_audit_checklist_items');
    }
};
