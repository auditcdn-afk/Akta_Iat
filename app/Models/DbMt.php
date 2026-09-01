<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbMt extends Model
{
    protected $table = 'db_mt';

    protected $fillable = ['nomor', 'nama_singkat', 'nama_peralatan', 'kode_peralatan', 'harga', 'jenis'];

    protected $casts = ['harga' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'            => $this->id,
            'nomor'         => $this->nomor,
            'namaSingkat'   => $this->nama_singkat,
            'namaPeralatan' => $this->nama_peralatan,
            'kodePeralatan' => $this->kode_peralatan,
            'harga'         => $this->harga !== null ? (float) $this->harga : null,
            'jenis'         => $this->jenis,
            'createdAt'     => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
