<?php

namespace App\Services\AnalisaZona\Temuan;

use App\Models\AnalisaTemuan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjalankan seluruh aturan pemeriksaan untuk satu unit usaha + periode,
 * lalu menyimpan hasilnya ke `analisa_temuan`.
 */
class TemuanService
{
    public function __construct(private readonly TemuanRuleRegistry $registry)
    {
    }

    /**
     * Bangun ulang temuan untuk satu unit usaha + periode. Temuan lama untuk
     * kombinasi itu DIHAPUS lebih dulu supaya tidak ada temuan basi yang
     * tertinggal setelah cabang mengirim data koreksi.
     *
     * @return int Jumlah temuan yang tersimpan.
     */
    public function rebuild(string $unitUsahaCode, string $periode, string $start, string $end): int
    {
        $hasil = [];

        foreach ($this->registry->all() as $rule) {
            try {
                foreach ($rule->evaluate($unitUsahaCode, $start, $end) as $temuan) {
                    $hasil[] = [
                        'unit_usaha_code' => $unitUsahaCode,
                        'periode'         => $periode,
                        'tanggal'         => $temuan->tanggal,
                        'kode_rule'       => $rule->kode(),
                        'judul'           => $temuan->judul,
                        'severity'        => $temuan->severity,
                        'nominal'         => $temuan->nominal,
                        'rekomendasi'     => $temuan->rekomendasi,
                        'detail_json'     => json_encode($temuan->detail),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }
            } catch (Throwable $e) {
                // Satu aturan yang gagal (mis. karena bentuk data tak terduga
                // dari cabang tertentu) tidak boleh menggagalkan seluruh
                // pemeriksaan — aturan lain tetap jalan dan temuannya tetap
                // sampai ke auditor. Kegagalannya dicatat supaya bisa
                // ditelusuri, bukan hilang diam-diam.
                Log::warning('Aturan temuan Analisa Zona gagal dijalankan', [
                    'rule'            => $rule->kode(),
                    'unit_usaha_code' => $unitUsahaCode,
                    'periode'         => $periode,
                    'pesan'           => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($unitUsahaCode, $periode, $hasil) {
            AnalisaTemuan::where('unit_usaha_code', $unitUsahaCode)
                ->where('periode', $periode)
                ->delete();

            foreach (array_chunk($hasil, 200) as $chunk) {
                DB::table('analisa_temuan')->insert($chunk);
            }
        });

        return count($hasil);
    }
}
