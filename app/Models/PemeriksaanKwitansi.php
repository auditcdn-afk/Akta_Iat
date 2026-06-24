<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanKwitansi extends Model
{
    protected $table = 'pemeriksaan_kwitansi';

    protected $fillable = [
        'plan_audit_id', 'tgl_audit', 'kwitansi_json',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tgl_audit'     => 'date',
        'kwitansi_json' => 'array',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'tglAudit'    => $this->tgl_audit?->format('Y-m-d'),
            'kwitansi'    => $this->kwitansi_json ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
