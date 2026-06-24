<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanHgp extends Model
{
    protected $table = 'pemeriksaan_hgp';

    protected $fillable = ['plan_audit_id', 'items_json', 'created_by', 'updated_by'];

    protected $casts = ['items_json' => 'array'];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'items'       => $this->items_json ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
