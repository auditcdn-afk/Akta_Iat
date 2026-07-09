<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPenilaian extends Model
{
    protected $table = 'plan_penilaian';

    protected $fillable = [
        'plan_audit_id',
        'role',
        'username',
        'display_name',
        'tgl_pemeriksaan',
        'catatan',
    ];

    protected $casts = [
        'tgl_pemeriksaan' => 'datetime',
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
            'role' => $this->role,
            'username' => $this->username,
            'displayName' => $this->display_name,
            'tglPemeriksaan' => optional($this->tgl_pemeriksaan)->toDateTimeString(),
            'catatan' => $this->catatan,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
