<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanCekFisik extends Model
{
    protected $table = 'pemeriksaan_cek_fisik';

    protected $fillable = ['plan_audit_id', 'data_json', 'created_by', 'updated_by'];

    protected $casts = ['data_json' => 'array'];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'data'        => $this->data_json ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
