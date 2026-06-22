<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['name', 'label', 'color', 'description', 'is_system', 'order'];

    protected $casts = [
        'is_system' => 'boolean',
        'order'     => 'integer',
    ];

    /** Ambil semua nama role (slug) — dipakai untuk validasi. */
    public static function allNames(): array
    {
        return static::orderBy('order')->pluck('name')->toArray();
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $this->label,
            'color'       => $this->color,
            'description' => $this->description,
            'isSystem'    => $this->is_system,
            'order'       => $this->order,
        ];
    }
}
