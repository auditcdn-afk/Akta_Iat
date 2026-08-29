<?php

namespace App\Console\Commands;

use App\Models\AnalisaAccConsumer;
use App\Models\AnalisaAccContract;
use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaUpload;
use Illuminate\Console\Command;

/**
 * Hapus data mentah RKK/ACC/LPK (berisi PII konsumen) yang lebih tua dari
 * masa retensi — tabel skor ringkasan (analisa_zona_scores) TIDAK ikut
 * terhapus supaya tren jangka panjang tetap bisa dilihat.
 *
 * `analisa_posisi_kas` (LHPBK) SENGAJA juga tidak ikut dipurge di sini —
 * lihat komentar di migrasinya: tidak ada PII, volumenya kecil (1 baris per
 * cabang per hari), dan justru paling berguna untuk tren jangka panjang.
 */
class PurgeAnalisaZonaRawData extends Command
{
    protected $signature = 'analisa-zona:purge-old-data {--days=60 : Data lebih tua dari sekian hari akan dihapus}';

    protected $description = 'Hapus data mentah Analisa Zona (RKK/ACC/LPK) yang sudah lewat masa retensi.';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $cutoff = now()->subDays($days)->toDateString();

        $deleted = [
            'rkk'         => AnalisaRkkTransaction::where('tanggal', '<', $cutoff)->delete(),
            'acc_konsumen'=> AnalisaAccConsumer::where('tanggal', '<', $cutoff)->delete(),
            'acc_kontrak' => AnalisaAccContract::where('tanggal', '<', $cutoff)->delete(),
            'acc_piutang' => AnalisaAccReceivable::where('tanggal_laporan', '<', $cutoff)->delete(),
            'lpk'         => AnalisaLpkPenjualan::where('tanggal', '<', $cutoff)->delete(),
            'uploads_log' => AnalisaUpload::where('tanggal', '<', $cutoff)->delete(),
        ];

        $total = array_sum($deleted);
        $this->info("Hapus data Analisa Zona lebih tua dari {$cutoff} ({$days} hari): total {$total} baris — " . json_encode($deleted));

        return self::SUCCESS;
    }
}
