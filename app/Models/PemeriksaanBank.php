<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanBank extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_bank';

    protected $fillable = [
        'plan_audit_id',
        'no_spt',
        'cabang',
        'jenis_audit',
        'nama_bank',
        'no_rekening',
        'saldo_buku',
        'saldo_bank',
        'selisih',
        'tgl_periksa',
        'auditee',
        'keterangan',
        'detail_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'saldo_buku' => 'decimal:2',
        'saldo_bank' => 'decimal:2',
        'selisih' => 'decimal:2',
        'tgl_periksa' => 'date',
        'detail_json' => 'array',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }
}
