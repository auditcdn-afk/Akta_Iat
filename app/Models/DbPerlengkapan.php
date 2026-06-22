<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPerlengkapan extends Model
{
    protected $table = 'db_perlengkapan';

    protected $fillable = ['tipe', 'nosin', 'aceh', 'riau', 'kepri', 'type'];

    public function toAktaArray(): array
    {
        return [
            'id'        => $this->id,
            'tipe'      => $this->tipe,
            'nosin'     => $this->nosin,
            'aceh'      => $this->aceh,
            'riau'      => $this->riau,
            'kepri'     => $this->kepri,
            'type'      => $this->type,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
