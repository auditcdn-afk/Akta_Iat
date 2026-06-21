<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keputusan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_audit_id')
                ->nullable()
                ->constrained('plan_audits')
                ->nullOnDelete();

            $table->string('no_spt', 80)->nullable()->index();
            $table->string('unit_usaha', 150)->nullable()->index();
            $table->string('jenis_audit', 80)->nullable()->index();

            $table->string('no_sk', 120)->index();

            $table->json('file_sk')->nullable();

            $table->string('status', 50)->default('pending_manajer')->index();

            $table->json('steps')->nullable();

            $table->string('uploaded_by', 100)->nullable()->index();
            $table->string('uploaded_by_name', 150)->nullable();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();

            $table->index(['plan_audit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keputusan');
    }
};
