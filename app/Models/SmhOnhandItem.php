<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmhOnhandItem extends Model
{
    protected $table = 'smh_onhand_items';

    protected $fillable = [
        'pemeriksaan_smh_id', 'no_mesin', 'no_rangka', 'no_spb',
        'tgl_spb', 'status_spb', 'umur', 'no_do', 'kode_model',
        'kode_model_intern', 'warna', 'kode_warna_intern', 'gudang', 'book',
        'status_fisik', 'keterangan_fisik', 'checked_at',
        'tgl_periksa', 'keterangan_kondisi', 'perlengkapan_json',
    ];

    protected $casts = [
        'tgl_spb'           => 'date',
        'tgl_periksa'       => 'date',
        'checked_at'        => 'datetime',
        'perlengkapan_json' => 'array',
    ];

    public function pemeriksaan(): BelongsTo
    {
        return $this->belongsTo(PemeriksaanSmh::class, 'pemeriksaan_smh_id');
    }
}
