<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master grading unik per (ID Grading, Wilayah) — bukan per ID Grading saja.
 *
 * Berkas master yang dipakai auditor MEMAKAI ULANG ID yang sama untuk wilayah
 * berbeda: G1151 ada untuk Aceh, Riau, dan Kepri sekaligus. Dengan indeks unik
 * pada id_grading saja, impor menimpa baris yang sudah masuk, dan yang tersisa
 * hanya wilayah terakhir di berkas. Pada berkas nyata 960 baris, 200 di
 * antaranya hilang tanpa pesan apa pun:
 *
 *   SO        435 baris -> 435   (ID-nya tidak dipakai ulang)
 *   CSC       225 baris -> 225
 *   WHS PART  165 baris ->  55   (110 hilang)
 *   WHS UNIT  135 baris ->  45   ( 90 hilang)
 *
 * Akibatnya audit gudang di Aceh & Riau kehilangan seluruh item grading-nya,
 * dan tidak ada satu pun tanda di layar bahwa datanya memang tidak pernah
 * sampai ke database.
 *
 * Pada berkas itu pasangan (id_grading, wilayah) unik untuk seluruh 960 baris,
 * jadi itulah kunci yang benar.
 */
return new class extends Migration {
    public function up(): void
    {
        // Sisa kembar dari impor-impor sebelumnya dibersihkan dulu (simpan id
        // terkecil), kalau tidak indeks uniknya tidak bisa dipasang.
        DB::statement('
            DELETE FROM db_grading
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM db_grading GROUP BY id_grading, wilayah
                ) AS simpan
            )
        ');

        if ($this->punyaIndeks('db_grading_id_grading_unique')) {
            Schema::table('db_grading', fn(Blueprint $t) => $t->dropUnique('db_grading_id_grading_unique'));
        }

        if (! $this->punyaIndeks('db_grading_id_grading_wilayah_unique')) {
            Schema::table('db_grading', fn(Blueprint $t) => $t->unique(['id_grading', 'wilayah'], 'db_grading_id_grading_wilayah_unique'));
        }
    }

    public function down(): void
    {
        if ($this->punyaIndeks('db_grading_id_grading_wilayah_unique')) {
            Schema::table('db_grading', fn(Blueprint $t) => $t->dropUnique('db_grading_id_grading_wilayah_unique'));
        }

        // Kembali ke keadaan semula perlu membuang kembarnya lebih dulu.
        DB::statement('
            DELETE FROM db_grading
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM db_grading GROUP BY id_grading
                ) AS simpan
            )
        ');

        if (! $this->punyaIndeks('db_grading_id_grading_unique')) {
            Schema::table('db_grading', fn(Blueprint $t) => $t->unique('id_grading', 'db_grading_id_grading_unique'));
        }
    }

    private function punyaIndeks(string $nama): bool
    {
        foreach (Schema::getIndexes('db_grading') as $indeks) {
            if ($indeks['name'] === $nama) return true;
        }
        return false;
    }
};
