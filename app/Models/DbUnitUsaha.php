<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbUnitUsaha extends Model
{
    protected $table = 'db_unit_usaha';

    // Columns: unit_usaha, wilayah, jenis
    protected $fillable = ['unit_usaha', 'wilayah', 'jenis'];

    public function toAktaArray(): array
    {
        return [
            'id'        => $this->id,
            'unitUsaha' => $this->unit_usaha,
            'wilayah'   => $this->wilayah,
            'jenis'     => $this->jenis,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
