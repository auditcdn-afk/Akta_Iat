<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop the old unique index on `kode` (jika ada) sebelum kolomnya dihapus.
        $this->dropIndexIfExists('db_unit_usaha', 'db_unit_usaha_kode_unique');

        Schema::table('db_unit_usaha', function (Blueprint $table) {
            // nama -> unit_usaha (pertahankan data lama)
            if (Schema::hasColumn('db_unit_usaha', 'nama') && ! Schema::hasColumn('db_unit_usaha', 'unit_usaha')) {
                $table->renameColumn('nama', 'unit_usaha');
            }
        });

        Schema::table('db_unit_usaha', function (Blueprint $table) {
            if (! Schema::hasColumn('db_unit_usaha', 'unit_usaha')) {
                $table->string('unit_usaha')->nullable();
            }
            if (! Schema::hasColumn('db_unit_usaha', 'wilayah')) {
                $table->string('wilayah')->nullable()->after('unit_usaha');
            }
            if (! Schema::hasColumn('db_unit_usaha', 'jenis')) {
                $table->string('jenis')->nullable()->after('wilayah');
            }
        });

        Schema::table('db_unit_usaha', function (Blueprint $table) {
            foreach (['kode', 'alamat', 'keterangan'] as $col) {
                if (Schema::hasColumn('db_unit_usaha', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Unique index baru: kombinasi unit_usaha + wilayah.
        if (! $this->indexExists('db_unit_usaha', 'db_unit_usaha_unit_usaha_wilayah_unique')) {
            $this->dedup('db_unit_usaha', ['unit_usaha', 'wilayah']);
            Schema::table('db_unit_usaha', function (Blueprint $table) {
                $table->unique(['unit_usaha', 'wilayah'], 'db_unit_usaha_unit_usaha_wilayah_unique');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('db_unit_usaha', 'db_unit_usaha_unit_usaha_wilayah_unique');

        Schema::table('db_unit_usaha', function (Blueprint $table) {
            if (! Schema::hasColumn('db_unit_usaha', 'kode')) {
                $table->string('kode')->nullable();
            }
            if (! Schema::hasColumn('db_unit_usaha', 'alamat')) {
                $table->text('alamat')->nullable();
            }
            if (! Schema::hasColumn('db_unit_usaha', 'keterangan')) {
                $table->text('keterangan')->nullable();
            }
        });

        Schema::table('db_unit_usaha', function (Blueprint $table) {
            foreach (['wilayah', 'jenis'] as $col) {
                if (Schema::hasColumn('db_unit_usaha', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('db_unit_usaha', 'unit_usaha') && ! Schema::hasColumn('db_unit_usaha', 'nama')) {
                $table->renameColumn('unit_usaha', 'nama');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $idx) {
            if ($idx['name'] === $index) return true;
        }
        return false;
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            Schema::table($table, fn(Blueprint $t) => $t->dropUnique($index));
        }
    }

    private function dedup(string $table, array $keys): void
    {
        // GROUP BY sudah memperlakukan NULL sebagai satu grup (setara dengan `<=>`
        // MySQL untuk kolom yang dibandingkan), dan bentuk "double derived table"
        // ini portable di SQLite/Postgres/MySQL (lihat migrasi ...000004 untuk
        // pola yang sama).
        $groupBy = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        DB::statement("
            DELETE FROM `{$table}`
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM `{$table}` GROUP BY {$groupBy}
                ) AS keep_ids
            )
        ");
    }
};
