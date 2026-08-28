<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaLpkPenjualan extends Model
{
    protected $table = 'analisa_lpk_penjualan';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal', 'kode_urut', 'kode_konsumen',
        'nama_konsumen', 'kode_finance', 'no_bukti', 'no_faktur', 'nominal',
        'kode_transaksi', 'jenis_transaksi', 'status_flag', 'keterangan', 'raw_line',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];

    public function isKwitansiGantung(): bool
    {
        return $this->kode_transaksi === 'CRGT';
    }
}
