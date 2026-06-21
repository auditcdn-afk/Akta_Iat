<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected $fillable = [
        'timestamp',
        'username',
        'display_name',
        'role',
        'action',
        'resource',
        'detail',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
