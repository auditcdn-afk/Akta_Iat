<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbHet extends Model
{
    protected $table = 'db_het';

    protected $fillable = ['kode', 'nama', 'harga_het', 'satuan', 'keterangan'];

    protected $casts = ['harga_het' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'hargaHet'   => $this->harga_het,
            'satuan'     => $this->satuan,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
