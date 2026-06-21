<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('audit_recommendation_id')
                ->constrained('audit_recommendations')
                ->cascadeOnDelete();

            $table->foreignId('plan_audit_id')
                ->nullable()
                ->constrained('plan_audits')
                ->nullOnDelete();

            $table->foreignId('audit_task_id')
                ->nullable()
                ->constrained('audit_tasks')
                ->nullOnDelete();

            $table->string('pica_no', 80)->nullable()->index();
            $table->string('title', 200)->nullable();

            $table->text('problem')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();

            $table->string('pic', 150)->nullable();

            $table->string('priority', 40)->default('sedang')->index();
            $table->string('status', 40)->default('open')->index();

            $table->date('target_date')->nullable()->index();
            $table->date('actual_date')->nullable();

            $table->json('evidence')->nullable();
            $table->text('notes')->nullable();

            $table->string('created_by', 100)->nullable()->index();
            $table->string('updated_by', 100)->nullable();

            $table->string('closed_by', 100)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_note')->nullable();

            $table->timestamps();

            $table->index(['audit_recommendation_id', 'status']);
            $table->index(['plan_audit_id', 'status']);
            $table->index(['audit_task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picas');
    }
};
