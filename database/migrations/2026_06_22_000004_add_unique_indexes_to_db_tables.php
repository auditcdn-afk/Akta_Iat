<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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

    private function addUniqueIfMissing(string $table, string $indexName, \Closure $add): void
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if (empty($indexes)) {
            Schema::table($table, $add);
        }
    }
};
