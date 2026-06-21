<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanKas extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_kas';

    protected $fillable = [
        'plan_audit_id',
        'no_spt',
        'cabang',
        'jenis_audit',
        'nama_pos',
        'saldo_fisik',
        'saldo_buku',
        'selisih',
        'keterangan',
        'detail_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'saldo_fisik' => 'decimal:2',
        'saldo_buku' => 'decimal:2',
        'selisih' => 'decimal:2',
        'detail_json' => 'array',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }
}
