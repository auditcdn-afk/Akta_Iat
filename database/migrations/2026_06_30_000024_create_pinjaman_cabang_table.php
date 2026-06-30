<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pinjaman_cabang')) {
            Schema::create('pinjaman_cabang', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_task_id')->index();
                $table->enum('jenis', ['BPK', 'BPB']);
                // BPK fields
                $table->json('cabang_realisasi')->nullable();  // array of selected cabang
                $table->string('no_spd')->nullable();
                $table->text('catatan')->nullable();
                $table->decimal('nominal', 18, 2)->default(0);
                $table->string('terbilang')->nullable();
                $table->string('bukti_file')->nullable();
                // BPB fields
                $table->string('departemen')->nullable()->default('Finance');
                // Birokrasi
                $table->string('status')->default('draft'); // draft,pending_koordinator,pending_manajer,pending_coo,pending_unit,pending_bpk,approved,rejected
                $table->json('approvals')->nullable(); // [{role, user, action, note, at}]
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjaman_cabang');
    }
};
