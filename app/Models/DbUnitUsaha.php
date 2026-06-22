<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbUnitUsaha extends Model
{
    protected $table = 'db_unit_usaha';

    protected $fillable = ['kode', 'nama', 'alamat', 'keterangan'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'alamat'     => $this->alamat,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
