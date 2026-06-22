<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPerlengkapan extends Model
{
    protected $table = 'db_perlengkapan';

    protected $fillable = ['kode', 'nama', 'satuan', 'qty', 'keterangan'];

    protected $casts = ['qty' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'nama'       => $this->nama,
            'satuan'     => $this->satuan,
            'qty'        => $this->qty,
            'keterangan' => $this->keterangan,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
