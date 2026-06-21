<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task audit terhubung ke plan_audits.
     */
    public function up(): void
    {
        Schema::create('audit_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')
                ->nullable()
                ->constrained('plan_audits')
                ->nullOnDelete();

            $table->string('judul', 200)->index();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('assigned_to', 150)->nullable()->index();
            $table->string('priority', 50)->default('normal')->index();
            $table->string('status', 50)->default('todo')->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('catatan')->nullable();

            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_tasks');
    }
};
