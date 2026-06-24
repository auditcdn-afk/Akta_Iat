<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpkbOnhandItem extends Model
{
    protected $table = 'bpkb_onhand_items';

    protected $fillable = [
        'plan_audit_id', 'no_bpkb', 'no_polisi', 'tgl_terima',
        'nama_pemilik', 'no_telepon', 'no_mesin', 'no_rangka',
        'jenis', 'umur', 'sudah_scan', 'keterangan', 'scan_at', 'created_by',
    ];

    protected $casts = [
        'tgl_terima'  => 'date',
        'scan_at'     => 'datetime',
        'sudah_scan'  => 'boolean',
        'umur'        => 'integer',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'noBpkb'      => $this->no_bpkb,
            'noPolisi'    => $this->no_polisi,
            'tglTerima'   => $this->tgl_terima?->format('d-m-Y'),
            'namaPemilik' => $this->nama_pemilik,
            'noTelepon'   => $this->no_telepon,
            'noMesin'     => $this->no_mesin,
            'noRangka'    => $this->no_rangka,
            'jenis'       => $this->jenis,
            'umur'        => $this->umur,
            'sudahScan'   => $this->sudah_scan,
            'keterangan'  => $this->keterangan,
            'scanAt'      => $this->scan_at?->toDateTimeString(),
        ];
    }
}
