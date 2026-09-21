<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "WO" di tabel HGP & AHM Oils menambah hitungan fisik, tapi namanya
 * tidak selalu "WO": tergantung cabangnya bisa berarti titipan, display,
 * retur, atau sebutan lain yang dipakai di lapangan. Judulnya karena itu bisa
 * diganti per plan audit -- yang dihitung tetap sama, hanya namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pemeriksaan_hgp', 'label_wo')) {
            return;
        }

        Schema::table('pemeriksaan_hgp', function (Blueprint $table) {
            $table->string('label_wo', 20)->nullable()->after('items_json');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pemeriksaan_hgp', 'label_wo')) {
            return;
        }

        Schema::table('pemeriksaan_hgp', fn(Blueprint $table) => $table->dropColumn('label_wo'));
    }
};
