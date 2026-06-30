<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PlanAudit;

class AuditGrading extends Model
{
    protected $table = 'audit_gradings';

    protected $fillable = [
        'plan_audit_id', 'id_grading', 'jenis', 'area',
        'bbnkb', 'fraud', 'jenis_fraud', 'keterangan_fraud',
        'details', 'total_nilai', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'jenis_fraud' => 'array',
        'details'     => 'array',
        'total_nilai' => 'float',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function toAktaArray(): array
    {
        return [
            'id'               => $this->id,
            'planAuditId'      => $this->plan_audit_id,
            'idGrading'        => $this->id_grading,
            'jenis'            => $this->jenis,
            'area'             => $this->area,
            'bbnkb'            => $this->bbnkb,
            'fraud'            => $this->fraud,
            'jenisFraud'       => $this->jenis_fraud ?? [],
            'keteranganFraud'  => $this->keterangan_fraud,
            'details'          => $this->details ?? [],
            'totalNilai'       => $this->total_nilai,
            'updatedAt'        => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
