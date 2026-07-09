<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sk_pembebanan', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('total_pembebanan');
            $table->string('finalized_by', 100)->nullable()->after('status');
            $table->string('finalized_by_name', 150)->nullable()->after('finalized_by');
            $table->timestamp('finalized_at')->nullable()->after('finalized_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('sk_pembebanan', function (Blueprint $table) {
            $table->dropColumn(['status', 'finalized_by', 'finalized_by_name', 'finalized_at']);
        });
    }
};
