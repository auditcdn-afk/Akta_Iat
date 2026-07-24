<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportAuditSummary extends Model
{
    protected $table = 'report_audit_summaries';

    protected $fillable = [
        'plan_audit_id',
        'no_spt', 'unit_usaha', 'cabang_area', 'jenis_audit', 'kepala_tim', 'anggota_tim', 'status_plan',
        'tgl_plan', 'tgl_mulai', 'tgl_selesai', 'jumlah_hari',
        'biaya_akomodasi', 'biaya_transportasi_darat', 'biaya_transportasi_laut', 'biaya_transportasi_udara',
        'biaya_konsumsi', 'biaya_laundry', 'biaya_pramenu', 'biaya_perobatan', 'biaya_komunikasi',
        'biaya_lain_lain', 'biaya_total',
        'fraud', 'jenis_fraud', 'keterangan_fraud',
        'nilai_grading', 'jumlah_item_grading',
        'no_sk', 'status_sk', 'tgl_sk_dibuat', 'tgl_sk_selesai',
        'jumlah_rekomendasi', 'rekomendasi_selesai', 'ringkasan_rekomendasi',
        'jumlah_pica', 'pica_closed', 'ringkasan_pica',
        'refreshed_at',
    ];

    protected $casts = [
        'tgl_plan' => 'date',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_sk_dibuat' => 'date',
        'tgl_sk_selesai' => 'date',
        'refreshed_at' => 'datetime',
        'biaya_akomodasi' => 'float',
        'biaya_transportasi_darat' => 'float',
        'biaya_transportasi_laut' => 'float',
        'biaya_transportasi_udara' => 'float',
        'biaya_konsumsi' => 'float',
        'biaya_laundry' => 'float',
        'biaya_pramenu' => 'float',
        'biaya_perobatan' => 'float',
        'biaya_komunikasi' => 'float',
        'biaya_lain_lain' => 'float',
        'biaya_total' => 'float',
        'nilai_grading' => 'float',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ReportAuditChecklistItem::class, 'plan_audit_id', 'plan_audit_id');
    }
}
