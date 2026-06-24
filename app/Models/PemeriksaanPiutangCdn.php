<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanPiutangCdn extends Model
{
    protected $table = 'pemeriksaan_piutang_cdn';

    protected $fillable = ['plan_audit_id', 'piutang_json', 'created_by', 'updated_by'];

    protected $casts = ['piutang_json' => 'array'];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'piutang'     => $this->piutang_json ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
