<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_lampiran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id');
            $table->json('files_json')->nullable();
            $table->string('merged_pdf')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_audit_id')->references('id')->on('plan_audit')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_lampiran');
    }
};
