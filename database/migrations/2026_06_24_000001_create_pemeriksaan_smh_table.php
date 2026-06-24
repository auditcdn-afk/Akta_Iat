<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pemeriksaan_smh', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('no_spt')->nullable();
            $table->string('cabang')->nullable();
            $table->date('tgl_onhand')->nullable();
            $table->unsignedInteger('total_unit')->default(0);
            $table->unsignedInteger('total_ditemukan')->default(0);
            $table->unsignedInteger('total_tidak_ditemukan')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('smh_onhand_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemeriksaan_smh_id')->constrained('pemeriksaan_smh')->cascadeOnDelete();
            $table->string('no_mesin')->nullable();
            $table->string('no_rangka')->nullable();
            $table->string('no_spb')->nullable();
            $table->date('tgl_spb')->nullable();
            $table->string('status_spb')->nullable();
            $table->unsignedInteger('umur')->nullable();
            $table->string('no_do')->nullable();
            $table->string('kode_model')->nullable();
            $table->string('kode_model_intern')->nullable();
            $table->string('warna')->nullable();
            $table->string('kode_warna_intern')->nullable();
            $table->string('gudang')->nullable();
            $table->string('book')->nullable();
            $table->string('status_fisik')->nullable(); // null=belum, 'ada', 'tidak_ada'
            $table->text('keterangan_fisik')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index('no_mesin');
            $table->index('no_rangka');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smh_onhand_items');
        Schema::dropIfExists('pemeriksaan_smh');
    }
};
