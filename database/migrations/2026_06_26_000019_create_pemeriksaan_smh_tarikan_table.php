<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pemeriksaan_smh_tarikan')) {
            Schema::create('pemeriksaan_smh_tarikan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_audit_id')->index();
                $table->json('items_json')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_smh_tarikan');
    }
};
