<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_tab_configs', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_audit', 100)->index();
            $table->string('tab_key', 50);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique(['jenis_audit', 'tab_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_tab_configs');
    }
};
