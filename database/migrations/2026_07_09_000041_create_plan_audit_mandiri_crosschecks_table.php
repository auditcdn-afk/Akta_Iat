<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_audit_mandiri_crosschecks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('hasil', 20); // ok | not_ok | selisih
            $table->text('catatan')->nullable();
            $table->string('username', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->timestamps();

            $table->unique('plan_audit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_audit_mandiri_crosschecks');
    }
};
