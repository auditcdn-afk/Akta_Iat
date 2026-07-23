<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealisasiDinas extends Model
{
    protected $table = 'realisasi_dinas';

    protected $fillable = [
        'plan_audit_id',
        'personil',
        'bukti_file',
        'status',
        'locked_at',
        'locked_by',
        'created_by',
    ];

    protected $casts = [
        'personil' => 'array',
        'bukti_file' => 'array',
        'locked_at' => 'datetime',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RealisasiDinasItem::class, 'realisasi_dinas_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'selesai';
    }

    public function toAktaArray(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return [
            'id' => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'cabang' => $this->planAudit?->cabang,
            'noSpt' => $this->planAudit?->no_spt,
            'personil' => $this->personil ?? [],
            'buktiFile' => $this->bukti_file,
            'status' => $this->status,
            'isLocked' => $this->isLocked(),
            'lockedAt' => optional($this->locked_at)->toDateTimeString(),
            'lockedBy' => $this->locked_by,
            'items' => $items->map->toAktaArray()->values()->all(),
            'totalNominal' => (float) $items->sum('nominal'),
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
