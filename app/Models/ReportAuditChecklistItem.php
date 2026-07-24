<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAuditChecklistItem extends Model
{
    protected $table = 'report_audit_checklist_items';

    public $timestamps = false;

    protected $fillable = [
        'plan_audit_id', 'audit_grading_id', 'jenis', 'urutan',
        'nama_pemeriksaan', 'current_condition', 'nilai', 'refreshed_at',
    ];

    protected $casts = [
        'nilai' => 'float',
        'refreshed_at' => 'datetime',
    ];

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }
}
