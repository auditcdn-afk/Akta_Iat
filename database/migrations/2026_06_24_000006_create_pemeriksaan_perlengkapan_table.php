<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_perlengkapan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id');
            $table->string('no_plan')->nullable();
            $table->string('nama_unit_usaha')->nullable();
            $table->string('nama_pemeriksa')->nullable();
            $table->date('tgl_periksa')->nullable();
            $table->string('jenis_perlengkapan')->nullable();
            $table->decimal('saldo', 15, 2)->default(0);
            $table->integer('fisik')->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->text('penjelasan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_audit_id')->references('id')->on('plan_audits')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_perlengkapan');
    }
};
