<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membersihkan daftar onhand SMH yang tergandakan, lalu menutup jalannya.
 *
 * Mengunggah file onhand dua kali beriringan membuat kedua permintaan
 * sama-sama menghapus isi lama yang belum sempat ditulis siapa pun, lalu
 * sama-sama memasukkan seluruh isi file — daftar unit jadi dobel (mis. 386
 * baris untuk file berisi 193 unit) sementara Total Unit di header tetap 193.
 * Nilai stok di Plafon dan Report Audit dihitung dari baris-baris ini, jadi
 * ikut jadi dua kali lipat.
 *
 * Perbaikan alurnya ada di PemeriksaanSmhController::simpanOnhand(). Migrasi
 * ini membereskan data yang terlanjur dobel dan memasang indeks unik supaya
 * database sendiri menolak penggandaan berikutnya, dari jalur mana pun.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->satukanHeaderKembar();
        $this->buangUnitKembar();
        $this->hitungUlangTotal();

        Schema::table('pemeriksaan_smh', function (Blueprint $table) {
            // Satu plan audit = satu daftar onhand. Dua header untuk plan yang
            // sama membuat Plafon & Report menjumlahkan dua daftar sekaligus.
            $table->unique('plan_audit_id', 'pemeriksaan_smh_plan_unik');
        });

        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->unique(['pemeriksaan_smh_id', 'no_mesin', 'no_rangka'], 'smh_onhand_items_unit_unik');
        });
    }

    public function down(): void
    {
        Schema::table('smh_onhand_items', function (Blueprint $table) {
            $table->dropUnique('smh_onhand_items_unit_unik');
        });

        Schema::table('pemeriksaan_smh', function (Blueprint $table) {
            $table->dropUnique('pemeriksaan_smh_plan_unik');
        });
    }

    /** Beberapa header untuk satu plan: itemnya dipindahkan ke satu header, sisanya dihapus. */
    private function satukanHeaderKembar(): void
    {
        $plans = DB::table('pemeriksaan_smh')
            ->select('plan_audit_id')
            ->groupBy('plan_audit_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('plan_audit_id');

        foreach ($plans as $planId) {
            $ids = DB::table('pemeriksaan_smh')->where('plan_audit_id', $planId)->orderBy('id')->pluck('id');

            // Yang disimpan: header dengan hasil pemeriksaan terbanyak — kalau
            // sama, yang paling banyak unitnya, lalu yang paling tua.
            $simpan = null;
            $skorSimpan = [-1, -1];
            foreach ($ids as $id) {
                $skor = [
                    DB::table('smh_onhand_items')->where('pemeriksaan_smh_id', $id)->whereNotNull('status_fisik')->count(),
                    DB::table('smh_onhand_items')->where('pemeriksaan_smh_id', $id)->count(),
                ];
                if ($skor > $skorSimpan) { $simpan = (int) $id; $skorSimpan = $skor; }
            }

            foreach ($ids as $id) {
                if ((int) $id === (int) $simpan) continue;
                DB::table('smh_onhand_items')->where('pemeriksaan_smh_id', $id)->update(['pemeriksaan_smh_id' => $simpan]);
                DB::table('pemeriksaan_smh')->where('id', $id)->delete();
            }
        }
    }

    /** Satu unit (no mesin + no rangka) cuma boleh sekali per daftar onhand. */
    private function buangUnitKembar(): void
    {
        $simpan = [];
        $buang  = [];

        DB::table('smh_onhand_items')
            ->select('id', 'pemeriksaan_smh_id', 'no_mesin', 'no_rangka', 'status_fisik', 'checked_at', 'keterangan_fisik', 'perlengkapan_json')
            ->orderBy('id')
            ->chunkById(1000, function ($baris) use (&$simpan, &$buang) {
                foreach ($baris as $r) {
                    $rapikan = fn($v) => strtoupper(preg_replace('/\s+/', '', (string) $v));
                    $kunci = $r->pemeriksaan_smh_id . '|' . $rapikan($r->no_mesin) . '|' . $rapikan($r->no_rangka);

                    // Yang dipertahankan: baris yang paling banyak hasil
                    // pemeriksaannya — jangan sampai kerja auditor yang dibuang.
                    $skor = ($r->status_fisik !== null ? 8 : 0)
                        + ($r->checked_at !== null ? 4 : 0)
                        + (! empty($r->perlengkapan_json) ? 2 : 0)
                        + (($r->keterangan_fisik ?? '') !== '' ? 1 : 0);

                    if (! isset($simpan[$kunci])) {
                        $simpan[$kunci] = ['id' => $r->id, 'skor' => $skor];
                        continue;
                    }
                    if ($skor > $simpan[$kunci]['skor']) {
                        $buang[] = $simpan[$kunci]['id'];
                        $simpan[$kunci] = ['id' => $r->id, 'skor' => $skor];
                    } else {
                        $buang[] = $r->id;
                    }
                }
            });

        foreach (array_chunk($buang, 500) as $sebagian) {
            DB::table('smh_onhand_items')->whereIn('id', $sebagian)->delete();
        }
    }

    /** Angka Total Unit / Ditemukan / Tidak Ditemukan disamakan dengan isi tabelnya. */
    private function hitungUlangTotal(): void
    {
        DB::table('pemeriksaan_smh')->select('id')->orderBy('id')->chunkById(200, function ($baris) {
            foreach ($baris as $r) {
                $hitung = DB::table('smh_onhand_items')
                    ->where('pemeriksaan_smh_id', $r->id)
                    ->selectRaw(
                        'COUNT(*) as total, ' .
                        "SUM(CASE WHEN status_fisik = 'ada' THEN 1 ELSE 0 END) as ditemukan, " .
                        "SUM(CASE WHEN status_fisik = 'tidak_ada' THEN 1 ELSE 0 END) as tidak_ditemukan"
                    )->first();

                DB::table('pemeriksaan_smh')->where('id', $r->id)->update([
                    'total_unit'            => (int) $hitung->total,
                    'total_ditemukan'       => (int) $hitung->ditemukan,
                    'total_tidak_ditemukan' => (int) $hitung->tidak_ditemukan,
                ]);
            }
        });
    }
};
