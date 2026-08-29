<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ringkasan posisi kas & bank harian per unit usaha, diekstrak dari PDF
// "Laporan Harian Posisi Bank dan Kas" (LHPBK) yang sudah rutin dicetak tiap
// cabang. Beda dengan RKK (cuma voucher kas kecil / biaya-biaya), file ini
// berisi REKONSILIASI KAS SEBENARNYA cabang per hari — saldo awal, semua
// penerimaan & pengeluaran, sampai saldo akhir. Indikator paling relevan:
// `saldo_akhir_kas` — kas yang masih dipegang cabang di akhir hari dan belum
// disetor ke bank; makin tinggi & makin sering tertahan, makin perlu
// diwaspadai/dikunjungi.
//
// SENGAJA tidak dimasukkan ke `analisa-zona:purge-old-data` (lihat komentar
// di command itu) — datanya tidak mengandung PII konsumen (beda dari
// RKK/ACC/LPK), volumenya sangat kecil (1 baris per cabang per hari), dan
// justru paling berguna untuk tren jangka panjang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_posisi_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('analisa_uploads')->cascadeOnDelete();
            $table->string('unit_usaha_code', 30);
            $table->date('tanggal');
            $table->decimal('saldo_awal_bank', 18, 2)->default(0);
            $table->decimal('saldo_akhir_bank', 18, 2)->default(0);
            $table->decimal('saldo_awal_kas', 18, 2)->default(0);
            $table->decimal('saldo_akhir_kas', 18, 2)->default(0);
            // Salinan teks penuh hasil ekstraksi PDF — jaring pengaman kalau
            // pemetaan baris/label perlu dikoreksi nanti, sama seperti
            // `raw_line` di parser RKK/ACC/LPK.
            $table->text('raw_text')->nullable();
            $table->timestamps();

            // BUKAN unique — kalau cabang mengunggah ulang LHPBK yang sudah
            // dikoreksi untuk tanggal yang sama (beda isi = beda source_hash
            // di analisa_uploads, jadi tidak kena skip-duplikat), baris baru
            // ditambahkan sebagai snapshot baru, bukan gagal insert. Skor
            // selalu memakai baris TERBARU (id terbesar) per tanggal.
            $table->index(['unit_usaha_code', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_posisi_kas');
    }
};
