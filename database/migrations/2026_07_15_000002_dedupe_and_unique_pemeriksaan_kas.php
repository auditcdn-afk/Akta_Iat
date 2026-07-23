<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PemeriksaanKasController::store() sebelumnya selalu INSERT baris baru
// tanpa mengecek apakah plan tsb sudah punya pemeriksaan kas — setiap kali
// tombol "Simpan Pemeriksaan Kas" ditekan dalam kondisi tertentu (klik ganda,
// dsb.), baris baru ikut dibuat alih-alih menimpa yang lama. Laporan PDF
// me-loop SEMUA baris untuk plan tsb, sehingga section "PEMERIKSAAN KAS"
// tercetak berulang. Migrasi ini membersihkan duplikat yang sudah terlanjur
// ada (menyisakan yang terbaru per plan_audit_id) dan menambah unique index
// supaya tidak bisa terjadi lagi di level database.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pemeriksaan_kas')) {
            return;
        }

        DB::statement("
            DELETE FROM pemeriksaan_kas
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) AS id FROM pemeriksaan_kas GROUP BY plan_audit_id
                ) AS keep_ids
            )
        ");

        if (!$this->hasIndexNamed('pemeriksaan_kas', 'pemeriksaan_kas_plan_audit_id_unique')) {
            Schema::table('pemeriksaan_kas', function (Blueprint $table) {
                $table->unique('plan_audit_id', 'pemeriksaan_kas_plan_audit_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pemeriksaan_kas')) {
            return;
        }

        if ($this->hasIndexNamed('pemeriksaan_kas', 'pemeriksaan_kas_plan_audit_id_unique')) {
            Schema::table('pemeriksaan_kas', function (Blueprint $table) {
                $table->dropUnique('pemeriksaan_kas_plan_audit_id_unique');
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
