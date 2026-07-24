<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanRsaHgp extends Model
{
    protected $table = 'pemeriksaan_rsa_hgp';

    protected $fillable = [
        'plan_audit_id', 'items_json', 'total_ditemukan', 'sample_size', 'created_by', 'updated_by',
    ];

    protected $casts = ['items_json' => 'array'];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'             => $this->id,
            'planAuditId'    => $this->plan_audit_id,
            'items'          => $this->items_json ?? [],
            'totalDitemukan' => $this->total_ditemukan,
            'sampleSize'     => $this->sample_size,
            'updatedAt'      => $this->updated_at?->toDateTimeString(),
        ];
    }
}
