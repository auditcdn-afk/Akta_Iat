<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_tasks', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('audit_tasks', 'finished_at')) {
                $table->dateTime('finished_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('audit_tasks', 'lampiran_path')) {
                $table->string('lampiran_path')->nullable()->after('finished_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_tasks', function (Blueprint $table) {
            foreach (['started_at', 'finished_at', 'lampiran_path'] as $col) {
                if (Schema::hasColumn('audit_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
