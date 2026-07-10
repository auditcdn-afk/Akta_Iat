<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PulsaRealisasi extends Model
{
    protected $table = 'pulsa_realisasi';

    protected $fillable = [
        'username',
        'nama',
        'jabatan',
        'tanggal',
        'nomor_hp',
        'operator',
        'nominal',
        'bon_file',
        'bulan',
        'tahun',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'bon_file' => 'array',
        'nominal' => 'float',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nama' => $this->nama,
            'jabatan' => $this->jabatan,
            'tanggal' => optional($this->tanggal)->toDateString(),
            'nomorHp' => $this->nomor_hp,
            'operator' => $this->operator,
            'nominal' => (float) $this->nominal,
            'bonFile' => $this->bon_file,
            'bulan' => $this->bulan,
            'tahun' => $this->tahun,
            'status' => $this->status,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
