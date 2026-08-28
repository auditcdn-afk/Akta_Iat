<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaRkkTransaction extends Model
{
    protected $table = 'analisa_rkk_transactions';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal', 'no_voucher', 'no_urut',
        'kode_akun', 'nama_akun', 'nominal', 'nama_supplier', 'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];
}
