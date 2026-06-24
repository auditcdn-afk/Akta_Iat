<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpkb_onhand_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->constrained('plan_audits')->cascadeOnDelete();
            $table->string('no_bpkb', 100);
            $table->string('no_polisi', 50)->nullable();
            $table->date('tgl_terima')->nullable();
            $table->string('nama_pemilik', 200)->nullable();
            $table->string('no_telepon', 50)->nullable();
            $table->string('no_mesin', 100)->nullable();
            $table->string('no_rangka', 100)->nullable();
            $table->string('jenis', 20)->nullable(); // REG / KDS
            $table->integer('umur')->nullable();      // hari
            $table->boolean('sudah_scan')->default(false);
            $table->string('keterangan', 255)->nullable();
            $table->timestamp('scan_at')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->unique(['plan_audit_id', 'no_bpkb']);
            $table->index(['plan_audit_id', 'sudah_scan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpkb_onhand_items');
    }
};
