<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_audits', function (Blueprint $table) {
            if (! Schema::hasColumn('plan_audits', 'tgl_plan')) {
                $table->date('tgl_plan')->nullable()->after('jenis_audit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_audits', function (Blueprint $table) {
            if (Schema::hasColumn('plan_audits', 'tgl_plan')) {
                $table->dropColumn('tgl_plan');
            }
        });
    }
};
