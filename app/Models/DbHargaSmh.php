<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbHargaSmh extends Model
{
    protected $table = 'db_harga_smh';

    protected $fillable = ['kode_model', 'nama_smh', 'harga'];

    protected $casts = ['harga' => 'float'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'kodeModel'  => $this->kode_model,
            'namaSmh'    => $this->nama_smh,
            'harga'      => $this->harga,
            'createdAt'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
