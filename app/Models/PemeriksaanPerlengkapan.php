<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanPerlengkapan extends Model
{
    protected $table = 'pemeriksaan_perlengkapan';

    protected $fillable = [
        'plan_audit_id', 'no_plan', 'nama_unit_usaha', 'nama_pemeriksa',
        'tgl_periksa', 'jenis_perlengkapan', 'saldo', 'fisik', 'selisih',
        'penjelasan', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tgl_periksa' => 'date',
        'saldo'       => 'float',
        'fisik'       => 'integer',
        'selisih'     => 'float',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'                => $this->id,
            'planAuditId'       => $this->plan_audit_id,
            'noPlan'            => $this->no_plan,
            'namaUnitUsaha'     => $this->nama_unit_usaha,
            'namaPemeriksa'     => $this->nama_pemeriksa,
            'tglPeriksa'        => $this->tgl_periksa?->toDateString(),
            'jenisPerlengkapan' => $this->jenis_perlengkapan,
            'saldo'             => $this->saldo,
            'fisik'             => $this->fisik,
            'selisih'           => $this->selisih,
            'penjelasan'        => $this->penjelasan,
            'updatedAt'         => $this->updated_at?->toDateTimeString(),
        ];
    }
}
