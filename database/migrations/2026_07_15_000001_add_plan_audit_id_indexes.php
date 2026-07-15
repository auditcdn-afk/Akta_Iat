<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Setiap tab pemeriksaan (Kas, SMH, Plafon, Materai, BPKB, ..., Grading) query
// dengan WHERE plan_audit_id = ? setiap kali tab dibuka. Sebagian besar tabel
// pemeriksaan_* dibuat tanpa index eksplisit di kolom ini. Di MySQL/InnoDB hal
// ini biasanya tertutupi karena constraint FK ikut membuat index, tapi aplikasi
// ini berjalan di atas SQLite (lihat .env DB_CONNECTION=sqlite) yang TIDAK
// otomatis mengindeks kolom FK — artinya semua query ini melakukan full table
// scan, dan makin lambat seiring bertambahnya jumlah audit & data pemeriksaan.
return new class extends Migration
{
    private const TABLES = [
        'audit_tasks',
        'pemeriksaan_smh',
        'pemeriksaan_perlengkapan',
        'pemeriksaan_materai',
        'pemeriksaan_bpkb_inproses',
        'pemeriksaan_kwitansi',
        'pemeriksaan_piutang_reguler',
        'pemeriksaan_piutang_cdn',
        'pemeriksaan_ttp_gantung',
        'pemeriksaan_cek_fisik',
        'pemeriksaan_mt',
        'pemeriksaan_hgp',
        'pemeriksaan_smh_tarikan',
        'pemeriksaan_hga',
        'pemeriksaan_lampiran',
        'audit_gradings',
        'sk_pembebanan',
        'plan_penilaian',
        'plan_audit_mandiri_crosschecks',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'plan_audit_id')) {
                continue;
            }

            if ($this->hasIndex($tableName, ['plan_audit_id'])) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->index('plan_audit_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'plan_audit_id')) {
                continue;
            }

            if (!$this->hasIndex($tableName, ['plan_audit_id'])) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['plan_audit_id']);
            });
        }
    }

    private function hasIndex(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }
        return false;
    }
};
