<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAuditMandiriCrosscheck extends Model
{
    protected $table = 'plan_audit_mandiri_crosschecks';

    protected $fillable = [
        'plan_audit_id',
        'hasil',
        'catatan',
        'username',
        'display_name',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'hasil' => $this->hasil,
            'catatan' => $this->catatan,
            'username' => $this->username,
            'displayName' => $this->display_name,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
