<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();   // slug: admin, manajer, auditor
            $table->string('label', 100);            // tampilan: Administrator, Manajer
            $table->string('color', 30)->default('slate'); // untuk badge: red, amber, blue, slate
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // true = tidak boleh dihapus
            $table->unsignedSmallInteger('order')->default(99);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
