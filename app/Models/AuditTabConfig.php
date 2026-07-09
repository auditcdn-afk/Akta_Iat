<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTabConfig extends Model
{
    protected $table = 'audit_tab_configs';

    protected $fillable = [
        'jenis_audit',
        'tab_key',
        'visible',
    ];

    protected $casts = [
        'visible' => 'boolean',
    ];
}
