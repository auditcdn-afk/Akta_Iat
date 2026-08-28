<?php

namespace App\Console\Commands;

use App\Services\AnalisaZona\ZonaRiskScoreService;
use Illuminate\Console\Command;

class RecomputeAnalisaZonaScores extends Command
{
    protected $signature = 'analisa-zona:recompute-scores {--periode= : Format YYYY-MM, default bulan berjalan}';

    protected $description = 'Hitung ulang skor risiko zona (unit usaha) untuk 1 periode — hasil disimpan, bukan dihitung live saat dashboard dibuka.';

    public function handle(ZonaRiskScoreService $service): int
    {
        $periode = $this->option('periode') ?: now()->format('Y-m');
        $count   = $service->recompute($periode);

        $this->info("Skor {$count} unit usaha untuk periode {$periode} berhasil dihitung ulang.");

        return self::SUCCESS;
    }
}
