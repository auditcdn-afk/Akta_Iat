<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Temuan analisa per unit usaha per periode — hasil pemeriksaan otomatis atas
// data RKK/ACC/LPK/LHPBK yang diupload cabang.
//
// Bedanya dengan `analisa_zona_scores`: skor menjawab "zona mana yang paling
// perlu dikunjungi" (satu angka untuk membanding-bandingkan cabang), sedangkan
// tabel ini menjawab "begitu sampai di sana, apa yang harus diperiksa" —
// tiap baris menyebut transaksi/tanggal/nominal yang konkret beserta tindakan
// yang disarankan. Dua-duanya diperlukan: skor untuk memilih tujuan,
// temuan untuk menyiapkan agenda pemeriksaan.
//
// Isinya DIBANGUN ULANG setiap kali skor dihitung ulang (hapus lalu isi lagi
// untuk unit usaha + periode yang bersangkutan), jadi tidak ada temuan basi
// yang tertinggal setelah cabang mengirim data koreksi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_temuan', function (Blueprint $table) {
            $table->id();
            $table->string('unit_usaha_code', 30);
            $table->string('periode', 7); // YYYY-MM
            // Tanggal kejadian yang ditemukan — null untuk temuan yang
            // memang tingkat periode, bukan tingkat hari.
            $table->date('tanggal')->nullable();
            $table->string('kode_rule', 40);
            $table->string('judul', 255);
            $table->string('severity', 10); // tinggi | sedang | rendah
            $table->decimal('nominal', 18, 2)->nullable();
            $table->text('rekomendasi');
            $table->json('detail_json')->nullable();
            $table->timestamps();

            $table->index(['unit_usaha_code', 'periode']);
            $table->index(['periode', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_temuan');
    }
};
