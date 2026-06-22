<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_grading', function (Blueprint $table) {
            $cols = DB::select('SHOW COLUMNS FROM db_grading');
            $existing = array_column($cols, 'Field');

            foreach (['kode', 'nama', 'grade', 'nilai_min', 'nilai_max', 'keterangan'] as $col) {
                if (in_array($col, $existing)) $table->dropColumn($col);
            }
        });

        Schema::table('db_grading', function (Blueprint $table) {
            $cols = DB::select('SHOW COLUMNS FROM db_grading');
            $existing = array_column($cols, 'Field');

            $add = [
                'id_grading'        => fn() => $table->string('id_grading')->nullable()->after('id'),
                'jenis'             => fn() => $table->string('jenis')->nullable()->after('id_grading'),
                'area'              => fn() => $table->string('area')->nullable()->after('jenis'),
                'nama_pemeriksaan'  => fn() => $table->text('nama_pemeriksaan')->nullable()->after('area'),
                'hasil_pemeriksaan' => fn() => $table->text('hasil_pemeriksaan')->nullable()->after('nama_pemeriksaan'),
                'nilai'             => fn() => $table->decimal('nilai', 5, 2)->nullable()->after('hasil_pemeriksaan'),
                'bknf'              => fn() => $table->string('bknf')->nullable()->after('nilai'),
                'pknf'              => fn() => $table->decimal('pknf', 10, 4)->nullable()->after('bknf'),
                'bkf'               => fn() => $table->string('bkf')->nullable()->after('pknf'),
                'pkf'               => fn() => $table->decimal('pkf', 10, 4)->nullable()->after('bkf'),
                'bnknf'             => fn() => $table->string('bnknf')->nullable()->after('pkf'),
                'pnknf'             => fn() => $table->decimal('pnknf', 10, 4)->nullable()->after('bnknf'),
                'bnkf'              => fn() => $table->string('bnkf')->nullable()->after('pnknf'),
                'pnkf'              => fn() => $table->decimal('pnkf', 10, 4)->nullable()->after('bnkf'),
            ];

            foreach ($add as $col => $fn) {
                if (!in_array($col, $existing)) $fn();
            }
        });
    }

    public function down(): void
    {
        Schema::table('db_grading', function (Blueprint $table) {
            $table->dropColumn(['id_grading','jenis','area','nama_pemeriksaan','hasil_pemeriksaan',
                'nilai','bknf','pknf','bkf','pkf','bnknf','pnknf','bnkf','pnkf']);
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('grade')->nullable();
            $table->decimal('nilai_min', 15, 2)->nullable();
            $table->decimal('nilai_max', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
        });
    }
};
