<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Katalog MT (db_mt) selama ini tidak punya harga sama sekali — beda dari
// katalog referensi lain yang memang dipakai untuk menghitung nilai selisih
// (db_harga_smh, db_het). Rekap Tools Rusak & Hilang di Report Audit PDF butuh
// kolom HARGA per alat (nilai kerugian kalau tool itu rusak/hilang), jadi
// katalognya perlu ikut menyimpan harga — bukan cuma nama & kode.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_mt', function (Blueprint $table) {
            $table->decimal('harga', 15, 2)->nullable()->after('kode_peralatan');
        });
    }

    public function down(): void
    {
        Schema::table('db_mt', function (Blueprint $table) {
            $table->dropColumn('harga');
        });
    }
};
