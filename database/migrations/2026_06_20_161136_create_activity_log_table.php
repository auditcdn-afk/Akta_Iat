<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log aktivitas aplikasi.
     * Menggantikan services/logger.js dari Express.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->nullable();
            $table->string('username', 100)->nullable();
            $table->string('display_name', 200)->nullable();
            $table->string('role', 50)->nullable();
            $table->string('action', 100)->nullable();
            $table->string('resource', 150)->nullable();
            $table->text('detail')->nullable();
            $table->string('ip', 100)->nullable();
            $table->text('user_agent')->nullable();

            $table->index('timestamp');
            $table->index('username');
            $table->index('action');
            $table->index('resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
