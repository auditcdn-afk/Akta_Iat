<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanTtpGantung extends Model
{
    protected $table = 'pemeriksaan_ttp_gantung';

    protected $fillable = ['plan_audit_id', 'tgl_audit', 'ttp_json', 'created_by', 'updated_by'];

    protected $casts = ['ttp_json' => 'array'];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'tglAudit'    => $this->tgl_audit,
            'ttp'         => $this->ttp_json ?? [],
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
