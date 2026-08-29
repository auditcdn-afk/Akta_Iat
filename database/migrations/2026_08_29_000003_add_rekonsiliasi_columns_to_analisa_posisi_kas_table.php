<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dua angka dari LHPBK yang dipakai untuk REKONSILIASI SILANG dengan file
// lain — terbukti cocok persis di data nyata SOTDB 26 Agustus 2026:
//
//   akun 21011 "KAS - Penerimaan Unit Uang Tunai" = Rp 127.852.000
//     ... sama persis dengan jumlah SELURUH baris LPK tanggal itu.
//   akun 22013 "KAS - Penggantian untuk kasbon"   = Rp 8.853.300
//     ... sama persis dengan jumlah baris RKK tanggal itu (voucher
//         0115 s/d 0123, dan rentang nomor itu memang disebut di
//         keterangannya).
//
// Karena hubungannya eksak (bukan kira-kira), SELISIH sekecil apa pun antara
// angka di sini dan jumlah file pasangannya adalah temuan audit yang nyata:
// ada transaksi yang tercatat di satu sisi tapi tidak di sisi lain.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisa_posisi_kas', function (Blueprint $table) {
            $table->decimal('penerimaan_unit_tunai', 18, 2)->default(0)->after('saldo_akhir_kas');
            $table->decimal('penggantian_kasbon', 18, 2)->default(0)->after('penerimaan_unit_tunai');
            $table->string('penggantian_kasbon_ket', 255)->nullable()->after('penggantian_kasbon');
        });
    }

    public function down(): void
    {
        Schema::table('analisa_posisi_kas', function (Blueprint $table) {
            $table->dropColumn(['penerimaan_unit_tunai', 'penggantian_kasbon', 'penggantian_kasbon_ket']);
        });
    }
};
