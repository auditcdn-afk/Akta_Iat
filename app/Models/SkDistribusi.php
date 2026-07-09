<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkDistribusi extends Model
{
    protected $table = 'sk_distribusi';

    protected $fillable = [
        'surat_keputusan_id',
        'user_username',
        'user_name',
        'status',
        'tanggapan',
        'tanggapan_poin',
        'file_tanggapan',
        'responded_at',
        'distributed_by',
        'distributed_by_name',
        'distributed_at',
    ];

    protected $casts = [
        'file_tanggapan' => 'array',
        'tanggapan_poin' => 'array',
        'responded_at' => 'datetime',
        'distributed_at' => 'datetime',
    ];

    public function suratKeputusan(): BelongsTo
    {
        return $this->belongsTo(SuratKeputusan::class, 'surat_keputusan_id');
    }
}
