<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_ttp_gantung', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id')->unique();
            $table->date('tgl_audit')->nullable();
            $table->json('ttp_json')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_audit_id')->references('id')->on('plan_audits')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_ttp_gantung');
    }
};
