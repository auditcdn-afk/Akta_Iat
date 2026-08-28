<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaZonaScore extends Model
{
    protected $table = 'analisa_zona_scores';

    protected $fillable = [
        'unit_usaha_code', 'periode', 'skor_kas_kecil', 'skor_pembiayaan',
        'skor_penjualan_piutang', 'skor_anomali', 'skor_total', 'detail_json', 'computed_at',
    ];

    protected $casts = [
        'skor_kas_kecil' => 'decimal:2',
        'skor_pembiayaan' => 'decimal:2',
        'skor_penjualan_piutang' => 'decimal:2',
        'skor_anomali' => 'decimal:2',
        'skor_total' => 'decimal:2',
        'detail_json' => 'array',
        'computed_at' => 'datetime',
    ];

    public function toAktaArray(): array
    {
        return [
            'unitUsahaCode' => $this->unit_usaha_code,
            'periode' => $this->periode,
            'skorKasKecil' => (float) $this->skor_kas_kecil,
            'skorPembiayaan' => (float) $this->skor_pembiayaan,
            'skorPenjualanPiutang' => (float) $this->skor_penjualan_piutang,
            'skorAnomali' => (float) $this->skor_anomali,
            'skorTotal' => (float) $this->skor_total,
            'detail' => $this->detail_json ?? [],
            'computedAt' => optional($this->computed_at)->toDateTimeString(),
        ];
    }
}
