<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_distribusi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('surat_keputusan_id')
                ->constrained('surat_keputusan')
                ->cascadeOnDelete();

            $table->string('user_username', 100)->index();
            $table->string('user_name', 150)->nullable();

            $table->string('status', 30)->default('pending')->index();
            $table->text('tanggapan')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->string('distributed_by', 100)->nullable();
            $table->string('distributed_by_name', 150)->nullable();
            $table->timestamp('distributed_at')->nullable();

            $table->timestamps();

            $table->unique(['surat_keputusan_id', 'user_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_distribusi');
    }
};
