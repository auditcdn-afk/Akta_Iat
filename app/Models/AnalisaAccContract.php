<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaAccContract extends Model
{
    protected $table = 'analisa_acc_contracts';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal', 'no_bukti', 'no_faktur',
        'kode_konsumen', 'jenis', 'harga_otr', 'dp', 'bunga', 'kode_sales',
        'status_flag', 'status_kredit', 'cara_bayar', 'raw_line',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga_otr' => 'decimal:2',
        'dp' => 'decimal:2',
        'bunga' => 'decimal:3',
    ];

    /** Rasio DP terhadap harga OTR (0..1) — DP tipis = indikator risiko. */
    public function getDpRatioAttribute(): ?float
    {
        if ((float) $this->harga_otr <= 0) {
            return null;
        }
        return round(((float) $this->dp) / ((float) $this->harga_otr), 4);
    }
}
