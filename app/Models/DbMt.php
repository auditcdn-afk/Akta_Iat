<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbMt extends Model
{
    protected $table = 'db_mt';

    protected $fillable = ['kode', 'nama', 'jenis', 'periode', 'keterangan'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'jenis'      => $this->jenis,
            'periode'    => $this->periode,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
