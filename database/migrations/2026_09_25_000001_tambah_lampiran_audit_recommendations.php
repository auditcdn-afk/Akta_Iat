<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Lampiran rekomendasi audit.
     *
     * Form Rekomendasi sudah lama punya "Upload File Lampiran", tapi berkasnya
     * tidak pernah tersimpan: yang diambil hanya NAMANYA, lalu ditempel sebagai
     * teks di akhir deskripsi ("Lampiran: REKAP SELISIH.pdf"). Berkasnya sendiri
     * dibuang begitu halaman ditutup -- itulah sebabnya tidak pernah muncul.
     *
     * Nama aslinya disimpan terpisah karena store() menamai berkas dengan nama
     * acak; yang perlu dibaca orang adalah nama aslinya.
     */
    public function up(): void
    {
        Schema::table('audit_recommendations', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_recommendations', 'lampiran_path')) {
                $table->string('lampiran_path')->nullable()->after('steps');
            }
            if (!Schema::hasColumn('audit_recommendations', 'lampiran_nama')) {
                $table->string('lampiran_nama')->nullable()->after('lampiran_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_recommendations', function (Blueprint $table) {
            if (Schema::hasColumn('audit_recommendations', 'lampiran_nama')) {
                $table->dropColumn('lampiran_nama');
            }
            if (Schema::hasColumn('audit_recommendations', 'lampiran_path')) {
                $table->dropColumn('lampiran_path');
            }
        });
    }
};
