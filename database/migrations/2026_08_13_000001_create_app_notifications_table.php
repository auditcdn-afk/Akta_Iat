<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifikasi in-app: "giliran Anda mengisi step X" untuk rekomendasi audit
     * (birokrasi) maupun SK, plus reminder harian selama step itu belum diisi.
     * Bernama app_notifications (bukan notifications) supaya tidak bentrok
     * dengan tabel bawaan Laravel Notifiable kalau suatu saat dipakai juga.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('notifiable_type', 40)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('step_key', 60)->nullable();
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
