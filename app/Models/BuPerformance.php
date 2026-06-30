<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuPerformance extends Model
{
    protected $table = 'bu_performances';

    protected $fillable = [
        'bulan', 'unit_usaha', 'auditor', 'penilaian', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'penilaian' => 'array',
    ];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'bulan'      => $this->bulan,
            'unitUsaha'  => $this->unit_usaha,
            'auditor'    => $this->auditor,
            'penilaian'  => $this->penilaian ?? [],
            'createdBy'  => $this->created_by,
            'updatedBy'  => $this->updated_by,
            'updatedAt'  => optional($this->updated_at)->format('Y-m-d'),
        ];
    }
}
