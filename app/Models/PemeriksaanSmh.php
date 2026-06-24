<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PemeriksaanSmh extends Model
{
    protected $table = 'pemeriksaan_smh';

    protected $fillable = [
        'plan_audit_id', 'no_spt', 'cabang', 'tgl_onhand',
        'total_unit', 'total_ditemukan', 'total_tidak_ditemukan',
        'keterangan', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tgl_onhand' => 'date',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SmhOnhandItem::class, 'pemeriksaan_smh_id');
    }
}
