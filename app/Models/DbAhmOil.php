<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbAhmOil extends Model
{
    protected $table = 'db_ahm_oils';

    protected $fillable = ['kode', 'nama', 'keterangan'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
