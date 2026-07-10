<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAuditMandiri extends Model
{
    protected $table = 'plan_audit_mandiris';

    protected $fillable = [
        'plan_audit_id',
        'no_plan',
        'urutan',
        'tahun_plan',
        'jenis_pemeriksaan',
        'jenis_audit',
        'cabang',
        'cabang_area',
        'tgl_plan',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tgl_plan' => 'date',
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
            'noPlan' => $this->no_plan,
            'jenisPemeriksaan' => $this->jenis_pemeriksaan,
            'jenisAudit' => $this->jenis_audit,
            'cabang' => $this->cabang,
            'cabangArea' => $this->cabang_area,
            'tglPlan' => optional($this->tgl_plan)->toDateString(),
            'status' => $this->status,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'crosscheck' => $this->planAudit?->crosscheck?->toAktaArray(),
        ];
    }
}
