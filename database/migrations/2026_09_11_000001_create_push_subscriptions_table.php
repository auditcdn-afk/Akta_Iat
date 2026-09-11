<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = satu perangkat yang sudah mengizinkan notifikasi.
 *
 * Seorang pengguna wajar punya beberapa baris (HP + laptop), dan sebaliknya
 * satu perangkat bisa berpindah pemilik kalau dipakai bergantian — karena itu
 * endpoint-nya unik secara global, bukan unik per pengguna: berlangganan ulang
 * dengan akun lain memindahkan kepemilikan baris, bukan menggandakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Endpoint bisa sangat panjang (FCM ratusan karakter) sehingga tidak
            // muat jadi kolom berindeks di MySQL. Yang diindeks hash-nya.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();

            $table->string('p256dh', 128);
            $table->string('auth', 32);
            $table->string('perangkat', 255)->nullable();

            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error', 255)->nullable();

            // Indeks user_id sudah dibuatkan foreignId()->constrained() di atas.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
