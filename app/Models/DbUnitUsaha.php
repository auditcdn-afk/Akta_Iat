<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbUnitUsaha extends Model
{
    protected $table = 'db_unit_usaha';

    // Actual columns: kode, nama, alamat (wilayah), keterangan (jenis/tipe)
    protected $fillable = ['kode', 'nama', 'alamat', 'keterangan'];

    public function toAktaArray(): array
    {
        return [
            'id'        => $this->id,
            'kode'      => $this->kode,
            'nama'      => $this->nama,
            'wilayah'   => $this->alamat,
            'jenis'     => $this->keterangan,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
