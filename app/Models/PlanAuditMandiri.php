<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAuditMandiri extends Model
{
    protected $table = 'plan_audit_mandiris';

    protected $fillable = [
        'no_plan',
        'urutan',
        'tahun_plan',
        'jenis_pemeriksaan',
        'jenis_audit',
        'cabang',
        'cabang_area',
        'tgl_plan',
        'catatan',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tgl_plan' => 'date',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'noPlan' => $this->no_plan,
            'jenisPemeriksaan' => $this->jenis_pemeriksaan,
            'jenisAudit' => $this->jenis_audit,
            'cabang' => $this->cabang,
            'cabangArea' => $this->cabang_area,
            'tglPlan' => optional($this->tgl_plan)->toDateString(),
            'catatan' => $this->catatan,
            'status' => $this->status,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
