<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom skor untuk indikator ke-5: posisi kas harian (LHPBK) — lihat
// ZonaRiskScoreService. Tabel sudah ada di produksi sejak fitur Analisa
// Zona pertama dirilis, jadi kolom baru ditambah lewat migrasi terpisah,
// bukan mengubah migrasi `create_analisa_zona_scores_table` yang sudah lama
// dijalankan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisa_zona_scores', function (Blueprint $table) {
            $table->decimal('skor_posisi_kas', 6, 2)->default(0)->after('skor_anomali');
        });
    }

    public function down(): void
    {
        Schema::table('analisa_zona_scores', function (Blueprint $table) {
            $table->dropColumn('skor_posisi_kas');
        });
    }
};
