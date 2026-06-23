<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAuditLog extends Model
{
    protected $table = 'plan_audit_logs';

    protected $fillable = [
        'plan_audit_id',
        'action',
        'from_status',
        'to_status',
        'actor',
        'actor_role',
        'note',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'action'     => $this->action,
            'fromStatus' => $this->from_status,
            'toStatus'   => $this->to_status,
            'actor'      => $this->actor,
            'actorRole'  => $this->actor_role,
            'note'       => $this->note,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
