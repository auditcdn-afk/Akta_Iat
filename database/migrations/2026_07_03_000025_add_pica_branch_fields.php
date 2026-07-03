<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            // Diisi auditor saat grading — dikirim otomatis ke PICA
            $table->text('current_condition')->nullable()->after('problem');
            // Diisi cabang
            $table->text('problem_identification')->nullable()->after('current_condition');
            $table->string('relation_ship', 150)->nullable()->after('pic');
            $table->string('relation_ship2', 150)->nullable()->after('relation_ship');
            // Identitas cabang yang harus mengisi
            $table->string('unit_usaha', 150)->nullable()->index()->after('relation_ship2');
            // Sumber: grading item index
            $table->string('source_type', 50)->nullable()->after('unit_usaha'); // 'grading'
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');  // audit_grading id
            $table->integer('source_item_idx')->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('picas', function (Blueprint $table) {
            $table->dropColumn([
                'current_condition', 'problem_identification',
                'relation_ship', 'relation_ship2', 'unit_usaha',
                'source_type', 'source_id', 'source_item_idx',
            ]);
        });
    }
};
