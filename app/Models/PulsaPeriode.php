<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PulsaPeriode extends Model
{
    protected $table = 'pulsa_periode';

    protected $fillable = [
        'tahun',
        'bulan',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'tahun' => $this->tahun,
            'bulan' => $this->bulan,
            'status' => $this->status,
            'closedBy' => $this->closed_by,
            'closedAt' => optional($this->closed_at)->toDateTimeString(),
        ];
    }
}
