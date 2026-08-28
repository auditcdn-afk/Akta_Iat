<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ringkasan skor risiko per unit usaha per periode (bulan) — sengaja
// TIDAK ikut kena purge retensi seperti tabel data mentah RKK/ACC/LPK,
// supaya tren jangka panjang tetap bisa dilihat walau detail transaksinya
// sudah dihapus.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_zona_scores', function (Blueprint $table) {
            $table->id();
            $table->string('unit_usaha_code', 30);
            $table->string('periode', 7); // format YYYY-MM
            $table->decimal('skor_kas_kecil', 6, 2)->default(0);
            $table->decimal('skor_pembiayaan', 6, 2)->default(0);
            $table->decimal('skor_penjualan_piutang', 6, 2)->default(0);
            $table->decimal('skor_anomali', 6, 2)->default(0);
            $table->decimal('skor_total', 6, 2)->default(0);
            $table->json('detail_json')->nullable(); // angka mentah pembentuk skor, untuk drill-down
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_usaha_code', 'periode']);
            $table->index(['periode', 'skor_total']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_zona_scores');
    }
};
