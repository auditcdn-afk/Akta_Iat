<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Karyawan extends Model
{
    protected $fillable = [
        'unit_usaha',
        'nama',
        'jabatan',
        'photo_path',
        'created_by',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'unitUsaha' => $this->unit_usaha,
            'nama' => $this->nama,
            'jabatan' => $this->jabatan,
            'photoUrl' => $this->photo_url,
            'createdBy' => $this->created_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
