<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_audit_logs')) {
            return;
        }

        Schema::create('plan_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('action', 30);            // created, advance, reject, execute
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->string('actor', 150)->nullable(); // username
            $table->string('actor_role', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['plan_audit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_audit_logs');
    }
};
