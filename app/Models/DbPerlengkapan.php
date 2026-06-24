<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPerlengkapan extends Model
{
    protected $table = 'db_perlengkapan';

    protected $fillable = ['kode', 'nama', 'satuan', 'qty', 'keterangan'];

    /** Kembalikan daftar perlengkapan sebagai array string */
    public function itemList(): array
    {
        if (empty($this->keterangan)) return [];
        return array_values(array_filter(array_map('trim', explode(',', $this->keterangan))));
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'kode'        => $this->kode,
            'nama'        => $this->nama,
            'satuan'      => $this->satuan,
            'qty'         => $this->qty,
            'keterangan'  => $this->keterangan,
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
