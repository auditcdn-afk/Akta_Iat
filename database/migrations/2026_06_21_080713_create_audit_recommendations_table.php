<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekomendasi audit.
     * Source lama: key akta_rekomendasi → tabel rekomendasi.
     * Laravel baru: audit_recommendations.
     */
    public function up(): void
    {
        Schema::create('audit_recommendations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_audit_id')
                ->nullable()
                ->constrained('plan_audits')
                ->nullOnDelete();

            $table->foreignId('audit_task_id')
                ->nullable()
                ->constrained('audit_tasks')
                ->nullOnDelete();

            $table->string('judul', 300);
            $table->text('deskripsi')->nullable();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('prioritas', 50)->default('sedang')->index();
            $table->string('status', 50)->default('draft')->index();
            $table->string('pic', 150)->nullable()->index();
            $table->date('deadline')->nullable()->index();
            $table->date('tgl_selesai')->nullable();

            $table->json('steps')->nullable();

            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['plan_audit_id', 'status']);
            $table->index(['audit_task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_recommendations');
    }
};
