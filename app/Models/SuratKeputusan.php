<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratKeputusan extends Model
{
    use HasFactory;

    protected $table = 'surat_keputusan';

    protected $fillable = [
        'plan_audit_id',
        'no_spt',
        'unit_usaha',
        'jenis_audit',
        'no_sk',
        'file_sk',
        'status',
        'steps',
        'uploaded_by',
        'uploaded_by_name',
        'uploaded_at',
    ];

    protected $casts = [
        'file_sk' => 'array',
        'steps' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function distribusi(): HasMany
    {
        return $this->hasMany(SkDistribusi::class, 'surat_keputusan_id');
    }
}
