<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanBpkbInproses extends Model
{
    protected $table = 'pemeriksaan_bpkb_inproses';

    protected $fillable = [
        'plan_audit_id', 'tgl_awal',
        'saldo_awal_fisik', 'penerimaan_fisik_json', 'pengeluaran_bpkb_json',
        'fisik_bpkb_hitung', 'keterangan_selisih',
        'filter_inproses', 'saldo_awal_inproses',
        'pendaftaran_bpkb_json', 'penyelesaian_inproses_json',
        'fisik_inproses_hitung',
        'ket_selisih_inproses_json', 'rincian_inproses_json',
        'onhand_bpkb', 'keterangan_selisih_onhand',
        'inproses_blocks_json',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tgl_awal'                  => 'date',
        'saldo_awal_fisik'          => 'integer',
        'fisik_bpkb_hitung'         => 'integer',
        'saldo_awal_inproses'       => 'integer',
        'fisik_inproses_hitung'     => 'integer',
        'onhand_bpkb'               => 'integer',
        'penerimaan_fisik_json'     => 'array',
        'pengeluaran_bpkb_json'     => 'array',
        'pendaftaran_bpkb_json'     => 'array',
        'penyelesaian_inproses_json'=> 'array',
        'ket_selisih_inproses_json' => 'array',
        'rincian_inproses_json'     => 'array',
        'inproses_blocks_json'      => 'array',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'                      => $this->id,
            'planAuditId'             => $this->plan_audit_id,
            'tglAwal'                 => $this->tgl_awal?->format('Y-m-d'),
            'saldoAwalFisik'          => $this->saldo_awal_fisik,
            'penerimaanFisik'         => $this->penerimaan_fisik_json ?? [],
            'pengeluaranBpkb'         => $this->pengeluaran_bpkb_json ?? [],
            'fisikBpkbHitung'         => $this->fisik_bpkb_hitung,
            'keteranganSelisih'       => $this->keterangan_selisih,
            'filterInproses'          => $this->filter_inproses,
            'saldoAwalInproses'       => $this->saldo_awal_inproses,
            'pendaftaranBpkb'         => $this->pendaftaran_bpkb_json ?? [],
            'penyelesaianInproses'    => $this->penyelesaian_inproses_json ?? [],
            'fisikInprosesHitung'     => $this->fisik_inproses_hitung,
            'ketSelisihInproses'      => $this->ket_selisih_inproses_json ?? [],
            'rincianInproses'         => $this->rincian_inproses_json ?? [],
            'onhandBpkb'              => $this->onhand_bpkb,
            'keteranganSelisihOnhand' => $this->keterangan_selisih_onhand,
            'inprosesBlocks'          => $this->inproses_blocks_json ?? [],
            'updatedAt'               => $this->updated_at?->toDateTimeString(),
        ];
    }
}
