<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_pembebanan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('surat_keputusan_id')
                ->constrained('surat_keputusan')
                ->cascadeOnDelete();

            $table->foreignId('plan_audit_id')
                ->nullable()
                ->constrained('plan_audits')
                ->nullOnDelete();

            $table->date('tgl_audit')->nullable();
            $table->string('no_sk', 120)->nullable();
            $table->string('unit_usaha', 150)->nullable();
            $table->string('jenis_unit', 20)->nullable()->comment('h1 atau h2_whs');

            $table->string('pimpinan_so', 150)->nullable();
            $table->string('pimpinan_csc', 150)->nullable();

            $table->json('personil');
            $table->decimal('total_pembebanan', 15, 2)->default(0);

            $table->string('created_by', 100)->nullable();
            $table->string('created_by_name', 150)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_pembebanan');
    }
};
