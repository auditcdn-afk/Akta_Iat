<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plan_audits')) {
            return;
        }

        // Plan baru kini langsung menunggu persetujuan Koordinator.
        // Naikkan plan lama yang masih 'draft' agar muncul untuk Koordinator.
        DB::table('plan_audits')
            ->where('status', 'draft')
            ->update(['status' => 'pending_koordinator']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('plan_audits')) {
            return;
        }

        DB::table('plan_audits')
            ->where('status', 'pending_koordinator')
            ->update(['status' => 'draft']);
    }
};
