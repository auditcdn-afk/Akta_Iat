<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_materai', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id');
            $table->string('jenis_materai', 100)->nullable();  // "Rp 10.000", "Rp 3.000", dll
            $table->integer('saldo_awal')->default(0);
            $table->integer('total_debet')->default(0);
            $table->integer('total_kredit')->default(0);
            $table->integer('saldo_akhir')->default(0);
            $table->integer('fisik')->default(0);
            $table->integer('selisih')->default(0);
            $table->json('transaksi_json')->nullable();         // array transaksi dari HTML
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->foreign('plan_audit_id')->references('id')->on('plan_audits')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_materai');
    }
};
