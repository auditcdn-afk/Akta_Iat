<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        'started_at',
        'finished_at',
        'lampiran_path',
        'due_date',
        'completed_at',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getLampiranUrlAttribute(): ?string
    {
        return $this->lampiran_path ? Storage::url($this->lampiran_path) : null;
    }

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
                'cabangArea' => $this->planAudit->cabang_area,
                'jenisAudit' => $this->planAudit->jenis_audit,
                'tglPlan' => optional($this->planAudit->tgl_plan)->format('Y-m-d'),
                'kepalaTim' => $this->planAudit->kepala_tim,
                'tim' => $this->planAudit->tim ?: [],
                'status' => $this->planAudit->status,
            ] : null,
            'judul' => $this->judul,
            'kategori' => $this->kategori,
            'assignedTo' => $this->assigned_to,
            'priority' => $this->priority,
            'status' => $this->status,
            'startedAt' => optional($this->started_at)->format('Y-m-d'),
            'finishedAt' => optional($this->finished_at)->format('Y-m-d'),
            'lampiranUrl' => $this->lampiran_url,
            'lampiranName' => $this->lampiran_path ? basename($this->lampiran_path) : null,
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
