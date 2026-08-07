<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nama Auditor & Nama Auditee untuk satu (plan_audit_id, tool) — lihat
 * PemeriksaanAuditorController untuk cara pengisiannya dan
 * Concerns\RequiresAuditorAuditee untuk cara tiap tool pemeriksaan
 * mewajibkannya sebelum data tool itu bisa disimpan.
 */
class PemeriksaanAuditor extends Model
{
    protected $table = 'pemeriksaan_auditors';

    protected $fillable = [
        'plan_audit_id',
        'tool',
        'nama_auditor',
        'nama_auditee',
        'created_by',
        'updated_by',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'tool'        => $this->tool,
            'namaAuditor' => $this->nama_auditor,
            'namaAuditee' => $this->nama_auditee,
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
