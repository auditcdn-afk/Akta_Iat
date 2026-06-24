<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_bpkb_inproses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->date('tgl_awal')->nullable();
            // Fisik BPKB
            $table->integer('saldo_awal_fisik')->default(0);
            $table->json('penerimaan_fisik_json')->nullable();   // [{keterangan, qty}]
            $table->json('pengeluaran_bpkb_json')->nullable();   // [{keterangan, qty}]
            $table->integer('fisik_bpkb_hitung')->nullable();
            $table->text('keterangan_selisih')->nullable();
            // Inproses
            $table->string('filter_inproses', 100)->nullable();
            $table->integer('saldo_awal_inproses')->default(0);
            $table->json('pendaftaran_bpkb_json')->nullable();   // [{keterangan, qty}]
            $table->json('penyelesaian_inproses_json')->nullable(); // [{keterangan, qty}]
            $table->integer('fisik_inproses_hitung')->nullable();
            // Keterangan selisih inproses & rincian per bulan
            $table->json('ket_selisih_inproses_json')->nullable(); // [{keterangan, qty}]
            $table->json('rincian_inproses_json')->nullable();     // [{bulan, qty}]
            // On Hand vs Fisik
            $table->integer('onhand_bpkb')->default(0);
            $table->text('keterangan_selisih_onhand')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->unique('plan_audit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_bpkb_inproses');
    }
};
