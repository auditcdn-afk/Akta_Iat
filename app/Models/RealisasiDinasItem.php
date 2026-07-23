<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealisasiDinasItem extends Model
{
    protected $table = 'realisasi_dinas_items';

    protected $fillable = [
        'realisasi_dinas_id',
        'jenis_pengeluaran',
        'catatan',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    public function realisasiDinas(): BelongsTo
    {
        return $this->belongsTo(RealisasiDinas::class, 'realisasi_dinas_id');
    }

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'jenisPengeluaran' => $this->jenis_pengeluaran,
            'catatan' => $this->catatan,
            'nominal' => (float) $this->nominal,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
