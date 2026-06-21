<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan_kas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_audit_id')
                ->constrained('plan_audits')
                ->cascadeOnDelete();

            $table->string('no_spt', 80)->nullable()->index();
            $table->string('cabang', 150)->nullable()->index();
            $table->string('jenis_audit', 80)->nullable()->index();

            $table->string('nama_pos', 200);

            $table->decimal('saldo_fisik', 18, 2)->default(0);
            $table->decimal('saldo_buku', 18, 2)->default(0);
            $table->decimal('selisih', 18, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->json('detail_json')->nullable();

            $table->string('created_by', 100)->nullable()->index();
            $table->string('updated_by', 100)->nullable();

            $table->timestamps();

            $table->index(['plan_audit_id', 'nama_pos']);
            $table->index(['plan_audit_id', 'selisih']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_kas');
    }
};
