<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel penyimpanan data legacy AKTA IAT.
     * Versi Express lama memakai PostgreSQL JSONB.
     * Versi lokal Laravel ini memakai MySQL JSON.
     */
    public function up(): void
    {
        Schema::create('app_data', function (Blueprint $table) {
            $table->id();
            $table->string('data_key', 100)->unique();
            $table->json('data_value')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('data_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_data');
    }
};
