<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            $table->string('forwarded_to_unit', 150)->nullable()->after('relation_ship2');
        });
    }

    public function down(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            $table->dropColumn('forwarded_to_unit');
        });
    }
};
