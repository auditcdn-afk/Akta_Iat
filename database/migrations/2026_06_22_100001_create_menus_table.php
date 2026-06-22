<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('code', 10);
            $table->string('route_name', 150)->nullable();
            $table->string('path', 200)->nullable();
            $table->string('icon', 50)->default('circle');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedSmallInteger('order')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
