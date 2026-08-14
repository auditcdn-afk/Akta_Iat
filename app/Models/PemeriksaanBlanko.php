<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Register Blanko yang Belum Digunakan (H1/H2) untuk satu (plan_audit_id, tool).
 * Lihat PemeriksaanBlankoController.
 */
class PemeriksaanBlanko extends Model
{
    protected $table = 'pemeriksaan_blankos';

    protected $fillable = [
        'plan_audit_id',
        'tool',
        'blanko_h1',
        'blanko_h2',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'blanko_h1' => 'array',
        'blanko_h2' => 'array',
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
            'tool'        => $this->tool,
            'blankoH1'    => $this->blanko_h1 ?? [],
            'blankoH2'    => $this->blanko_h2 ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
