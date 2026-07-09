<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_audits', function (Blueprint $table) {
            $table->boolean('is_mandiri')->default(false)->after('status');
        });

        Schema::table('plan_audit_mandiris', function (Blueprint $table) {
            $table->foreignId('plan_audit_id')->nullable()->after('id')
                ->constrained('plan_audits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_audit_mandiris', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_audit_id');
        });

        Schema::table('plan_audits', function (Blueprint $table) {
            $table->dropColumn('is_mandiri');
        });
    }
};
