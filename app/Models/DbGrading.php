<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbGrading extends Model
{
    protected $table = 'db_grading';

    protected $fillable = [
        'id_grading', 'jenis', 'wilayah', 'nama_pemeriksaan', 'hasil_pemeriksaan',
        'nilai', 'bknf', 'pknf', 'bkf', 'pkf', 'bnknf', 'pnknf', 'bnkf', 'pnkf',
    ];

    protected $casts = [
        'nilai' => 'float',
        'pknf'  => 'float',
        'pkf'   => 'float',
        'pnknf' => 'float',
        'pnkf'  => 'float',
    ];

    public function toAktaArray(): array
    {
        return [
            'id'               => $this->id,
            'idGrading'        => $this->id_grading,
            'jenis'            => $this->jenis,
            'wilayah'          => $this->wilayah,
            'namaPemeriksaan'  => $this->nama_pemeriksaan,
            'hasilPemeriksaan' => $this->hasil_pemeriksaan,
            'nilai'            => $this->nilai,
            'bknf'             => $this->bknf,
            'pknf'             => $this->pknf,
            'bkf'              => $this->bkf,
            'pkf'              => $this->pkf,
            'bnknf'            => $this->bnknf,
            'pnknf'            => $this->pnknf,
            'bnkf'             => $this->bnkf,
            'pnkf'             => $this->pnkf,
            'createdAt'        => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
