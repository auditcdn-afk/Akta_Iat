<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaAccReceivable extends Model
{
    protected $table = 'analisa_acc_receivables';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal_laporan', 'kode_konsumen',
        'no_bukti', 'tanggal_transaksi', 'kode_sales', 'nominal', 'raw_line',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date',
        'tanggal_transaksi' => 'date',
        'nominal' => 'decimal:2',
    ];

    /** Umur piutang (hari) sejak tanggal transaksi sampai tanggal laporan. */
    public function getUmurHariAttribute(): ?int
    {
        if (!$this->tanggal_transaksi || !$this->tanggal_laporan) {
            return null;
        }
        return $this->tanggal_transaksi->diffInDays($this->tanggal_laporan);
    }
}
