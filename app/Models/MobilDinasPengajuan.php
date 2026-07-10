<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilDinasPengajuan extends Model
{
    protected $table = 'mobil_dinas_pengajuan';

    protected $fillable = [
        'supir_request',
        'tanggal_berangkat',
        'tanggal_pulang',
        'pic_mobil',
        'spd_file',
        'status',
        'catatan_manajer',
        'approved_by',
        'approved_at',
        'nama_supir',
        'plat_mobil',
        'jenis_mobil',
        'completed_by',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'tanggal_pulang' => 'date',
        'pic_mobil' => 'array',
        'spd_file' => 'array',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'supirRequest' => $this->supir_request,
            'tanggalBerangkat' => optional($this->tanggal_berangkat)->toDateString(),
            'tanggalPulang' => optional($this->tanggal_pulang)->toDateString(),
            'picMobil' => $this->pic_mobil ?? [],
            'spdFile' => $this->spd_file,
            'status' => $this->status,
            'catatanManajer' => $this->catatan_manajer,
            'approvedBy' => $this->approved_by,
            'approvedAt' => optional($this->approved_at)->toDateTimeString(),
            'namaSupir' => $this->nama_supir,
            'platMobil' => $this->plat_mobil,
            'jenisMobil' => $this->jenis_mobil,
            'completedBy' => $this->completed_by,
            'completedAt' => optional($this->completed_at)->toDateTimeString(),
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
