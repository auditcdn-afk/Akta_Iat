<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAudit extends Model
{
    protected $table = 'plan_audits';

    protected $fillable = [
        'no_spt',
        'cabang',
        'cabang_area',
        'jenis_audit',
        'tgl_mulai',
        'tgl_selesai',
        'kepala_tim',
        'tim',
        'status',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tim' => 'array',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'noSpt' => $this->no_spt,
            'cabang' => $this->cabang,
            'cabangArea' => $this->cabang_area,
            'jenisAudit' => $this->jenis_audit,
            'tglMulai' => optional($this->tgl_mulai)->format('Y-m-d'),
            'tglSelesai' => optional($this->tgl_selesai)->format('Y-m-d'),
            'kepalaTim' => $this->kepala_tim,
            'tim' => $this->tim ?: [],
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AuditTask::class, 'plan_audit_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'plan_audit_id');
    }
}
