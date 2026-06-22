<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPlafon extends Model
{
    protected $table = 'db_plafon';

    protected $fillable = ['kode', 'nama', 'nilai', 'keterangan'];

    protected $casts = ['nilai' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'kode'        => $this->kode,
            'nama'        => $this->nama,
            'nilai'       => $this->nilai,
            'keterangan'  => $this->keterangan,
            'createdAt'   => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
