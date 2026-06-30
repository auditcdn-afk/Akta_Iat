<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_gradings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_audit_id')->index();
            $table->string('id_grading')->nullable();
            $table->string('jenis')->nullable();       // Cabang, Bengkel, WHS PART, WHS UNIT, Lain-Lain
            $table->string('area')->nullable();         // RRI, dll dari cabang_area
            $table->string('bbnkb')->default('N');     // N / Y
            $table->string('fraud')->default('N');      // N / Y
            $table->json('jenis_fraud')->nullable();
            $table->text('keterangan_fraud')->nullable();
            $table->json('details')->nullable();        // array item pemeriksaan
            $table->decimal('total_nilai', 8, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_gradings');
    }
};
