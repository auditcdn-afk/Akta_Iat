<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan_bank', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_audit_id')
                ->constrained('plan_audits')
                ->cascadeOnDelete();

            $table->string('no_spt', 80)->nullable()->index();
            $table->string('cabang', 150)->nullable()->index();
            $table->string('jenis_audit', 80)->nullable()->index();

            $table->string('nama_bank', 150)->nullable()->index();
            $table->string('no_rekening', 80)->nullable()->index();

            $table->decimal('saldo_buku', 18, 2)->default(0);
            $table->decimal('saldo_bank', 18, 2)->default(0);
            $table->decimal('selisih', 18, 2)->default(0);

            $table->date('tgl_periksa')->nullable();
            $table->string('auditee', 150)->nullable();
            $table->text('keterangan')->nullable();

            $table->json('detail_json')->nullable();

            $table->string('created_by', 100)->nullable()->index();
            $table->string('updated_by', 100)->nullable();

            $table->timestamps();

            $table->index(['plan_audit_id', 'nama_bank']);
            $table->index(['plan_audit_id', 'selisih']);
            $table->index(['plan_audit_id', 'tgl_periksa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_bank');
    }
};
