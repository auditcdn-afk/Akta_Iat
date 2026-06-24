<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanMaterai extends Model
{
    protected $table = 'pemeriksaan_materai';

    protected $fillable = [
        'plan_audit_id', 'jenis_materai',
        'saldo_awal', 'total_debet', 'total_kredit', 'saldo_akhir',
        'fisik', 'selisih', 'transaksi_json',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'transaksi_json' => 'array',
        'saldo_awal'     => 'integer',
        'total_debet'    => 'integer',
        'total_kredit'   => 'integer',
        'saldo_akhir'    => 'integer',
        'fisik'          => 'integer',
        'selisih'        => 'integer',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'            => $this->id,
            'planAuditId'   => $this->plan_audit_id,
            'jenisMaterai'  => $this->jenis_materai,
            'saldoAwal'     => $this->saldo_awal,
            'totalDebet'    => $this->total_debet,
            'totalKredit'   => $this->total_kredit,
            'saldoAkhir'    => $this->saldo_akhir,
            'fisik'         => $this->fisik,
            'selisih'       => $this->selisih,
            'transaksi'     => $this->transaksi_json ?? [],
            'updatedAt'     => $this->updated_at?->toDateTimeString(),
        ];
    }
}
