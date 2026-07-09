<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_audit_mandiris', function (Blueprint $table) {
            $table->id();
            $table->string('no_plan', 100)->unique();
            $table->unsignedSmallInteger('urutan');
            $table->unsignedSmallInteger('tahun_plan');
            $table->enum('jenis_pemeriksaan', ['audit_mandiri', 'sertijab'])->default('audit_mandiri');
            $table->string('jenis_audit', 100);
            $table->string('cabang', 150)->nullable();
            $table->string('cabang_area', 150)->nullable();
            $table->date('tgl_plan')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_audit_mandiris');
    }
};
