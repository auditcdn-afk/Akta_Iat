<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppData extends Model
{
    protected $table = 'app_data';

    public $timestamps = false;

    protected $fillable = [
        'data_key',
        'data_value',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'data_value' => 'array',
        'updated_at' => 'datetime',
    ];
}
