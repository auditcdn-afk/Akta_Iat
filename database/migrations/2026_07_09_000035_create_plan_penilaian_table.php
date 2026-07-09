<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_penilaian', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_audit_id')
                ->constrained('plan_audits')
                ->cascadeOnDelete();

            $table->string('role', 30);
            $table->string('username', 100)->nullable();
            $table->string('display_name', 150)->nullable();

            $table->timestamp('tgl_pemeriksaan')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->unique(['plan_audit_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_penilaian');
    }
};
