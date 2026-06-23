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
        return ! empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function dedup(string $table, array $keys): void
    {
        $on = collect($keys)->map(fn($k) => "t1.`{$k}` <=> t2.`{$k}`")->implode(' AND ');
        DB::statement("DELETE t1 FROM `{$table}` t1 INNER JOIN `{$table}` t2 ON {$on} WHERE t1.id > t2.id");
    }
};
