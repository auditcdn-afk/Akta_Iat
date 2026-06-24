<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_hgp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id');
            $table->json('items_json')->nullable();   // [ { sparepart, saldoAwal, fisik, akhir, selisih, keterangan, tgl } ]
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('plan_audit_id')->references('id')->on('plan_audits')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_hgp');
    }
};
