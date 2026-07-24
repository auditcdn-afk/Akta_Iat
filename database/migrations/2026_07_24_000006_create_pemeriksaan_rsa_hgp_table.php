<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RSA HGP & AHM Oils = varian "Random Sampling Audit" dari tool pemeriksaan_hgp.
// Bedanya: saat import Excel, sistem tidak menyimpan seluruh baris hasil parse
// (bisa 1000+ item), melainkan hanya sample-nya (30 item, atau 50 item untuk
// unit usaha ber-role WHS) — lihat RsaHgpController::sampleSize().
// Disimpan di tabel terpisah (bukan dicampur ke pemeriksaan_hgp) supaya kedua
// tool tetap independen: mengisi RSA HGP tidak menimpa data HGP & AHM Oils biasa
// untuk plan audit yang sama, begitu juga sebaliknya.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_rsa_hgp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id');
            $table->json('items_json')->nullable();   // [ { sparepart, saldoAkhir, fisik, akhir, selisih, keterangan, tgl } ] — hasil sample
            $table->unsignedInteger('total_ditemukan')->nullable();  // jumlah item sebelum disampling
            $table->unsignedInteger('sample_size')->nullable();      // ukuran sample yang dipakai (30 / 50)
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('plan_audit_id')->references('id')->on('plan_audits')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_rsa_hgp');
    }
};
