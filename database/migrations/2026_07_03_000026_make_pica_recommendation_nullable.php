<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            // PICA yang dibuat otomatis dari grading tidak punya recommendation
            $table->unsignedBigInteger('audit_recommendation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_recommendation_id')->nullable(false)->change();
        });
    }
};
