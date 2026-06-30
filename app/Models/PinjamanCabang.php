<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinjamanCabang extends Model
{
    protected $table = 'pinjaman_cabang';

    protected $fillable = [
        'audit_task_id', 'jenis', 'cabang_realisasi', 'no_spd', 'catatan',
        'nominal', 'terbilang', 'bukti_file', 'departemen',
        'status', 'approvals', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'cabang_realisasi' => 'array',
        'approvals'        => 'array',
        'nominal'          => 'float',
    ];

    // Urutan birokrasi per jenis
    public const FLOW_BPK = [
        'pending_koordinator', 'pending_manajer', 'pending_coo', 'pending_unit', 'pending_bpk', 'approved',
    ];
    public const FLOW_BPB = [
        'pending_koordinator', 'pending_manajer', 'pending_bpk', 'approved',
    ];

    public function auditTask(): BelongsTo
    {
        return $this->belongsTo(AuditTask::class, 'audit_task_id');
    }

    public function nextStatus(): ?string
    {
        $flow  = $this->jenis === 'BPK' ? self::FLOW_BPK : self::FLOW_BPB;
        $idx   = array_search($this->status, $flow);
        return $idx !== false && isset($flow[$idx + 1]) ? $flow[$idx + 1] : null;
    }

    public function toAktaArray(): array
    {
        return [
            'id'               => $this->id,
            'auditTaskId'      => $this->audit_task_id,
            'jenis'            => $this->jenis,
            'cabangRealisasi'  => $this->cabang_realisasi ?? [],
            'noSpd'            => $this->no_spd,
            'catatan'          => $this->catatan,
            'nominal'          => $this->nominal,
            'terbilang'        => $this->terbilang,
            'buktFile'         => $this->bukti_file,
            'departemen'       => $this->departemen,
            'status'           => $this->status,
            'approvals'        => $this->approvals ?? [],
            'nextStatus'       => $this->nextStatus(),
            'createdBy'        => $this->created_by,
            'updatedAt'        => optional($this->updated_at)->format('Y-m-d H:i'),
        ];
    }
}
