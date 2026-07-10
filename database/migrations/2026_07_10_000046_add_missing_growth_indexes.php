<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bu_performances')) {
            Schema::table('bu_performances', function (Blueprint $table) {
                if (!$this->hasIndex('bu_performances', ['bulan', 'unit_usaha'])) {
                    $table->index(['bulan', 'unit_usaha']);
                }
            });
        }

        if (Schema::hasTable('audit_tasks') && Schema::hasColumn('audit_tasks', 'created_at')) {
            Schema::table('audit_tasks', function (Blueprint $table) {
                if (!$this->hasIndex('audit_tasks', ['created_at'])) {
                    $table->index('created_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bu_performances')) {
            Schema::table('bu_performances', function (Blueprint $table) {
                $table->dropIndex(['bulan', 'unit_usaha']);
            });
        }

        if (Schema::hasTable('audit_tasks')) {
            Schema::table('audit_tasks', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }
    }

    private function hasIndex(string $table, array $columns): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }
        return false;
    }
};
