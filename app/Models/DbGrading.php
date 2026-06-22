<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbGrading extends Model
{
    protected $table = 'db_grading';

    protected $fillable = ['kode', 'nama', 'grade', 'nilai_min', 'nilai_max', 'keterangan'];

    protected $casts = ['nilai_min' => 'float', 'nilai_max' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'grade'      => $this->grade,
            'nilaiMin'   => $this->nilai_min,
            'nilaiMax'   => $this->nilai_max,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
