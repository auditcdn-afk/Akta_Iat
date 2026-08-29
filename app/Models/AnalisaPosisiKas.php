<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaPosisiKas extends Model
{
    protected $table = 'analisa_posisi_kas';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal',
        'saldo_awal_bank', 'saldo_akhir_bank',
        'saldo_awal_kas', 'saldo_akhir_kas', 'raw_text',
    ];

    protected $casts = [
        'tanggal'          => 'date',
        'saldo_awal_bank'  => 'decimal:2',
        'saldo_akhir_bank' => 'decimal:2',
        'saldo_awal_kas'   => 'decimal:2',
        'saldo_akhir_kas'  => 'decimal:2',
    ];
}
