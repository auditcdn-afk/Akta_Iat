<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditRecommendation extends Model
{
    protected $table = 'audit_recommendations';

    protected $fillable = [
        'plan_audit_id',
        'audit_task_id',
        'judul',
        'deskripsi',
        'kategori',
        'prioritas',
        'status',
        'pic',
        'deadline',
        'tgl_selesai',
        'steps',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'deadline' => 'date',
        'tgl_selesai' => 'date',
        'steps' => 'array',
        'approved_at' => 'datetime',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function auditTask(): BelongsTo
    {
        return $this->belongsTo(AuditTask::class, 'audit_task_id');
    }

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'auditTaskId' => $this->audit_task_id,
            'planAudit' => $this->planAudit ? [
                'id' => $this->planAudit->id,
                'noSpt' => $this->planAudit->no_spt,
                'cabang' => $this->planAudit->cabang,
                'jenisAudit' => $this->planAudit->jenis_audit,
                'status' => $this->planAudit->status,
            ] : null,
            'auditTask' => $this->auditTask ? [
                'id' => $this->auditTask->id,
                'judul' => $this->auditTask->judul,
                'kategori' => $this->auditTask->kategori,
                'status' => $this->auditTask->status,
            ] : null,
            'judul' => $this->judul,
            'deskripsi' => $this->deskripsi,
            'kategori' => $this->kategori,
            'prioritas' => $this->prioritas,
            'status' => $this->status,
            'pic' => $this->pic,
            'deadline' => optional($this->deadline)->format('Y-m-d'),
            'tglSelesai' => optional($this->tgl_selesai)->format('Y-m-d'),
            'steps' => $this->steps ?: [],
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'approvedBy' => $this->approved_by,
            'approvedAt' => optional($this->approved_at)->toDateTimeString(),
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
