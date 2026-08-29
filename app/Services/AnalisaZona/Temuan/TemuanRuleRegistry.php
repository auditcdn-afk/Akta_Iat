<?php

namespace App\Services\AnalisaZona\Temuan;

use App\Services\AnalisaZona\Temuan\Rules\DpTipisRule;
use App\Services\AnalisaZona\Temuan\Rules\DuplikatDataRule;
use App\Services\AnalisaZona\Temuan\Rules\KasBelumDisetorRule;
use App\Services\AnalisaZona\Temuan\Rules\KontrakTanpaPenjualanRule;
use App\Services\AnalisaZona\Temuan\Rules\PiutangMenunggakRule;
use App\Services\AnalisaZona\Temuan\Rules\RekonKasbonRkkRule;
use App\Services\AnalisaZona\Temuan\Rules\RekonPenerimaanLpkRule;

/** Daftar aturan pemeriksaan — tambah aturan baru cukup daftarkan di sini. */
class TemuanRuleRegistry
{
    /** @var TemuanRuleInterface[] */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            // Dua rekonsiliasi silang didahulukan: hubungan angkanya eksak
            // (terbukti cocok sampai rupiah terakhir di data nyata), jadi
            // selisih di sini paling kuat sebagai temuan.
            new RekonPenerimaanLpkRule(),
            new RekonKasbonRkkRule(),
            new KasBelumDisetorRule(),
            new PiutangMenunggakRule(),
            new KontrakTanpaPenjualanRule(),
            new DpTipisRule(),
            new DuplikatDataRule(),
        ];
    }

    /** @return TemuanRuleInterface[] */
    public function all(): array
    {
        return $this->rules;
    }
}
