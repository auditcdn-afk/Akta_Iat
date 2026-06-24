<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $table->text('satuan')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('db_perlengkapan', function (Blueprint $table) {
            $table->string('satuan', 50)->nullable()->change();
        });
    }
};
