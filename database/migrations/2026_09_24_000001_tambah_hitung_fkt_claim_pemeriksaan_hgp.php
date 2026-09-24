<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar per plan audit: ikut menghitung Faktur Belum Kutip & Claim ke dalam
 * Saldo Akhir / Fisik.
 *
 * Aturan ini dipakai HANYA untuk data gudang (WHS) yang sudah terlanjur
 * diinput, dan ke depan tidak berlaku lagi. Karena itu dibuat sebagai saklar
 * yang MATI secara bawaan, bukan dipaku di kode: plan baru otomatis tidak
 * memakainya, dan mematikannya nanti tidak perlu deploy ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemeriksaan_hgp', function (Blueprint $table) {
            if (! Schema::hasColumn('pemeriksaan_hgp', 'hitung_fkt_claim')) {
                $table->boolean('hitung_fkt_claim')->default(false)->after('label_wo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pemeriksaan_hgp', function (Blueprint $table) {
            if (Schema::hasColumn('pemeriksaan_hgp', 'hitung_fkt_claim')) {
                $table->dropColumn('hitung_fkt_claim');
            }
        });
    }
};
