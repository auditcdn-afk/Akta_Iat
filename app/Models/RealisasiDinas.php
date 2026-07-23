<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealisasiDinas extends Model
{
    protected $table = 'realisasi_dinas';

    protected $fillable = [
        'plan_audit_id',
        'tanggal_settlement',
        'personil',
        'jenis_pengeluaran',
        'catatan',
        'nominal',
        'bukti_file',
        'created_by',
    ];

    protected $casts = [
        'tanggal_settlement' => 'date',
        'personil' => 'array',
        'nominal' => 'decimal:2',
        'bukti_file' => 'array',
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
            'cabang' => $this->planAudit?->cabang,
            'noSpt' => $this->planAudit?->no_spt,
            'tanggalSettlement' => optional($this->tanggal_settlement)->toDateString(),
            'personil' => $this->personil ?? [],
            'jenisPengeluaran' => $this->jenis_pengeluaran,
            'catatan' => $this->catatan,
            'nominal' => (float) $this->nominal,
            'buktiFile' => $this->bukti_file,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
