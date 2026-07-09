<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkPembebanan extends Model
{
    protected $table = 'sk_pembebanan';

    protected $fillable = [
        'surat_keputusan_id',
        'plan_audit_id',
        'tgl_audit',
        'no_sk',
        'unit_usaha',
        'jenis_unit',
        'pimpinan_so',
        'pimpinan_csc',
        'personil',
        'total_pembebanan',
        'status',
        'finalized_by',
        'finalized_by_name',
        'finalized_at',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'tgl_audit' => 'date',
        'personil' => 'array',
        'total_pembebanan' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function suratKeputusan(): BelongsTo
    {
        return $this->belongsTo(SuratKeputusan::class, 'surat_keputusan_id');
    }

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }
}
