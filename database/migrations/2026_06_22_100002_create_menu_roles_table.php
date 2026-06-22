<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            // Role disimpan sebagai string agar tidak terikat tabel roles terpisah
            // dan kompatibel dengan kolom role yang sudah ada di tabel users.
            $table->string('role', 50);
            $table->timestamps();

            $table->unique(['menu_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_roles');
    }
};
