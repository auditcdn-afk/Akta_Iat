<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaAccConsumer extends Model
{
    protected $table = 'analisa_acc_consumers';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal', 'kode_konsumen', 'nama',
        'no_hp', 'nik', 'tgl_lahir', 'no_rangka', 'dusun', 'kecamatan',
        'kabupaten', 'desa', 'kode_pos', 'kode_wilayah', 'raw_line',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tgl_lahir' => 'date',
    ];

    /** NIK tersamar untuk tampilan list, mis. "1107********0001". */
    public function getNikMaskedAttribute(): ?string
    {
        if (!$this->nik || strlen($this->nik) < 8) {
            return $this->nik;
        }
        $nik = $this->nik;
        return substr($nik, 0, 4) . str_repeat('*', strlen($nik) - 8) . substr($nik, -4);
    }

    /** No. HP tersamar untuk tampilan list. */
    public function getNoHpMaskedAttribute(): ?string
    {
        if (!$this->no_hp || strlen($this->no_hp) < 6) {
            return $this->no_hp;
        }
        $hp = $this->no_hp;
        return substr($hp, 0, 4) . str_repeat('*', strlen($hp) - 6) . substr($hp, -2);
    }
}
