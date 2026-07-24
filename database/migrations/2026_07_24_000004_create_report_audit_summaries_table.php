<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel hasil "flatten" (materialized) dari plan_audits + realisasi_dinas +
// audit_gradings + surat_keputusan + audit_recommendations + picas.
// Diisi ulang secara terjadwal oleh App\Services\ReportAuditFlattener,
// BUKAN dihitung on-the-fly saat request — supaya Power BI (Import mode)
// dan fitur export Excel di aplikasi cukup baca tabel ini tanpa join berat
// ke tabel transaksional yang dipakai auditor sehari-hari.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_audit_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_audit_id')->unique()->constrained('plan_audits')->cascadeOnDelete();

            $table->string('no_spt', 100)->nullable()->index();
            $table->string('unit_usaha', 150)->nullable()->index();
            $table->string('cabang_area', 150)->nullable()->index();
            $table->string('jenis_audit', 100)->nullable()->index();
            $table->string('kepala_tim', 150)->nullable();
            $table->text('anggota_tim')->nullable();
            $table->string('status_plan', 50)->nullable();

            $table->date('tgl_plan')->nullable();
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->integer('jumlah_hari')->nullable();

            $table->decimal('biaya_akomodasi', 15, 2)->default(0);
            $table->decimal('biaya_transportasi_darat', 15, 2)->default(0);
            $table->decimal('biaya_transportasi_laut', 15, 2)->default(0);
            $table->decimal('biaya_transportasi_udara', 15, 2)->default(0);
            $table->decimal('biaya_konsumsi', 15, 2)->default(0);
            $table->decimal('biaya_laundry', 15, 2)->default(0);
            $table->decimal('biaya_pramenu', 15, 2)->default(0);
            $table->decimal('biaya_perobatan', 15, 2)->default(0);
            $table->decimal('biaya_komunikasi', 15, 2)->default(0);
            $table->decimal('biaya_lain_lain', 15, 2)->default(0);
            $table->decimal('biaya_total', 15, 2)->default(0);

            $table->string('fraud', 10)->nullable();
            $table->text('jenis_fraud')->nullable();
            $table->text('keterangan_fraud')->nullable();

            $table->decimal('nilai_grading', 8, 2)->nullable();
            $table->integer('jumlah_item_grading')->default(0);

            $table->string('no_sk', 120)->nullable();
            $table->string('status_sk', 50)->nullable();
            $table->date('tgl_sk_dibuat')->nullable();
            $table->date('tgl_sk_selesai')->nullable();

            $table->integer('jumlah_rekomendasi')->default(0);
            $table->integer('rekomendasi_selesai')->default(0);
            $table->text('ringkasan_rekomendasi')->nullable();

            $table->integer('jumlah_pica')->default(0);
            $table->integer('pica_closed')->default(0);
            $table->text('ringkasan_pica')->nullable();

            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['tgl_mulai', 'tgl_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_audit_summaries');
    }
};
