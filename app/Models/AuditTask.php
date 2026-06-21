<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditTask extends Model
{
    protected $table = 'audit_tasks';

    protected $fillable = [
        'plan_audit_id',
        'judul',
        'kategori',
        'assigned_to',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
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
            'planAudit' => $this->planAudit ? [
                'id' => $this->planAudit->id,
                'noSpt' => $this->planAudit->no_spt,
                'cabang' => $this->planAudit->cabang,
                'jenisAudit' => $this->planAudit->jenis_audit,
                'status' => $this->planAudit->status,
            ] : null,
            'judul' => $this->judul,
            'kategori' => $this->kategori,
            'assignedTo' => $this->assigned_to,
            'priority' => $this->priority,
            'status' => $this->status,
            'dueDate' => optional($this->due_date)->format('Y-m-d'),
            'completedAt' => optional($this->completed_at)->toDateTimeString(),
            'catatan' => $this->catatan,
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'audit_task_id');
    }
}
