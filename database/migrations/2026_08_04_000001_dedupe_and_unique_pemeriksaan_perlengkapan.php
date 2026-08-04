<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PemeriksaanPerlengkapanController::store() sebelumnya selalu INSERT baris
// baru tanpa mengecek apakah plan tsb sudah punya baris untuk jenis
// perlengkapan yang sama — setiap kali jenis yang sama diinput ulang (mis.
// auditor scan ulang item yang sama), baris baru ikut dibuat alih-alih
// menimpa yang lama. Karena total "Saldo" di tabel menjumlah SEMUA baris,
// duplikat ini bikin Saldo ikut menggelembung padahal fisiknya tidak
// bertambah. Migrasi ini membersihkan duplikat yang sudah terlanjur ada
// (menyisakan yang terbaru per plan_audit_id + jenis_perlengkapan) dan
// menambah unique index supaya tidak bisa terjadi lagi di level database.
// Sama seperti perbaikan pemeriksaan_kas di 2026_07_15_000002.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pemeriksaan_perlengkapan')) {
            return;
        }

        DB::statement("
            DELETE FROM pemeriksaan_perlengkapan
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) AS id FROM pemeriksaan_perlengkapan
                    GROUP BY plan_audit_id, jenis_perlengkapan
                ) AS keep_ids
            )
        ");

        if (!$this->hasIndexNamed('pemeriksaan_perlengkapan', 'pemeriksaan_perlengkapan_plan_jenis_unique')) {
            Schema::table('pemeriksaan_perlengkapan', function (Blueprint $table) {
                $table->unique(['plan_audit_id', 'jenis_perlengkapan'], 'pemeriksaan_perlengkapan_plan_jenis_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pemeriksaan_perlengkapan')) {
            return;
        }

        if ($this->hasIndexNamed('pemeriksaan_perlengkapan', 'pemeriksaan_perlengkapan_plan_jenis_unique')) {
            Schema::table('pemeriksaan_perlengkapan', function (Blueprint $table) {
                $table->dropUnique('pemeriksaan_perlengkapan_plan_jenis_unique');
            });
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
