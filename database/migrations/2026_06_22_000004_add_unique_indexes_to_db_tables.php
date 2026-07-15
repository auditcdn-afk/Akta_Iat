<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Deduplicate each table (keep lowest id per unique key) before adding indexes
        $this->dedup('db_harga_smh',   ['kode_model']);
        $this->dedup('db_plafon',      ['kode']);
        $this->dedup('db_perlengkapan',['nosin']);
        $this->dedup('db_unit_usaha',  ['kode']);
        $this->dedup('db_grading',     ['id_grading']);
        $this->dedup('db_mt',          ['kode_peralatan', 'jenis']);
        $this->dedup('db_het',         ['kode']);

        $this->addUniqueIfMissing('db_harga_smh',  'db_harga_smh_kode_model_unique',  fn(Blueprint $t) => $t->unique('kode_model'));
        $this->addUniqueIfMissing('db_plafon',      'db_plafon_kode_unique',            fn(Blueprint $t) => $t->unique('kode'));
        $this->addUniqueIfMissing('db_perlengkapan','db_perlengkapan_nosin_unique',      fn(Blueprint $t) => $t->unique('nosin'));
        $this->addUniqueIfMissing('db_unit_usaha',  'db_unit_usaha_kode_unique',         fn(Blueprint $t) => $t->unique('kode'));
        $this->addUniqueIfMissing('db_grading',     'db_grading_id_grading_unique',      fn(Blueprint $t) => $t->unique('id_grading'));
        $this->addUniqueIfMissing('db_mt',          'db_mt_kode_peralatan_jenis_unique', fn(Blueprint $t) => $t->unique(['kode_peralatan', 'jenis']));
        $this->addUniqueIfMissing('db_het',         'db_het_kode_unique',                fn(Blueprint $t) => $t->unique('kode'));
    }

    public function down(): void
    {
        Schema::table('db_harga_smh',   fn(Blueprint $t) => $t->dropUnique('db_harga_smh_kode_model_unique'));
        Schema::table('db_plafon',      fn(Blueprint $t) => $t->dropUnique('db_plafon_kode_unique'));
        Schema::table('db_perlengkapan',fn(Blueprint $t) => $t->dropUnique('db_perlengkapan_nosin_unique'));
        Schema::table('db_unit_usaha',  fn(Blueprint $t) => $t->dropUnique('db_unit_usaha_kode_unique'));
        Schema::table('db_grading',     fn(Blueprint $t) => $t->dropUnique('db_grading_id_grading_unique'));
        Schema::table('db_mt',          fn(Blueprint $t) => $t->dropUnique('db_mt_kode_peralatan_jenis_unique'));
        Schema::table('db_het',         fn(Blueprint $t) => $t->dropUnique('db_het_kode_unique'));
    }

    private function dedup(string $table, array $keys): void
    {
        // Delete duplicate rows keeping the one with the lowest id. Written as a
        // "double derived table" subquery so it works on SQLite/Postgres/MySQL
        // alike (a plain correlated subquery referencing the same table in the
        // FROM clause is rejected by MySQL: "target table for update in FROM").
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

    private function addUniqueIfMissing(string $table, string $indexName, \Closure $add): void
    {
        if (!$this->hasIndexNamed($table, $indexName)) {
            Schema::table($table, $add);
        }
    }

    private function hasIndexNamed(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) return true;
        }
        return false;
    }
};
