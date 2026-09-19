<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur PICA punya empat tahap: auditor menulis Current Condition, unit usaha
 * mengisi tindak lanjutnya, pihak Relation Ship menuliskan tanggapan, lalu unit
 * usaha melakukan Re-Chek.
 *
 * Tiga kolom terakhir tahap itu tidak pernah punya migration -- kodenya sudah
 * memakainya, tapi tabelnya tidak punya kolomnya, jadi begitu tanggapan atau
 * Re-Chek disimpan yang keluar cuma "Server Error".
 *
 * Tanggapan Relation Ship juga menumpang di kolom problem_identification milik
 * unit usaha, sehingga isian unit usaha hilang tertimpa begitu tanggapan masuk.
 * Tanggapan sekarang punya kolomnya sendiri.
 */
return new class extends Migration
{
    /** @var array<string, callable(Blueprint): void> */
    private function kolom(): array
    {
        return [
            'tanggapan_pica'      => fn(Blueprint $t) => $t->text('tanggapan_pica')->nullable(),
            'forwarded_filled_at' => fn(Blueprint $t) => $t->timestamp('forwarded_filled_at')->nullable(),
            'recheck_note'        => fn(Blueprint $t) => $t->text('recheck_note')->nullable(),
            'recheck_deadline'    => fn(Blueprint $t) => $t->date('recheck_deadline')->nullable(),
            'recheck_file'        => fn(Blueprint $t) => $t->string('recheck_file', 255)->nullable(),
            'recheck_at'          => fn(Blueprint $t) => $t->timestamp('recheck_at')->nullable(),
        ];
    }

    public function up(): void
    {
        $tambah = array_filter(
            $this->kolom(),
            fn($_, $nama) => !Schema::hasColumn('picas', $nama),
            ARRAY_FILTER_USE_BOTH
        );

        if ($tambah) {
            Schema::table('picas', function (Blueprint $table) use ($tambah) {
                foreach ($tambah as $buat) {
                    $buat($table);
                }
            });
        }

        // Baris lama: tanggapan Relation Ship masih tersimpan di
        // problem_identification. Salin ke kolomnya sendiri; yang lama tidak
        // dihapus supaya tidak ada tulisan orang yang ikut hilang.
        if (Schema::hasColumn('picas', 'forwarded_filled_at') && in_array('tanggapan_pica', array_keys($tambah), true)) {
            DB::table('picas')
                ->whereNotNull('forwarded_filled_at')
                ->whereNull('tanggapan_pica')
                ->update(['tanggapan_pica' => DB::raw('problem_identification')]);
        }
    }

    public function down(): void
    {
        $ada = array_filter(
            array_keys($this->kolom()),
            fn($nama) => Schema::hasColumn('picas', $nama)
        );

        if ($ada) {
            Schema::table('picas', fn(Blueprint $table) => $table->dropColumn(array_values($ada)));
        }
    }
};
