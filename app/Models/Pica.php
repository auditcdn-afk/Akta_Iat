<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pica extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_recommendation_id',
        'plan_audit_id',
        'audit_task_id',
        'pica_no',
        'title',
        'problem',
        'current_condition',
        'problem_identification',
        'root_cause',
        'corrective_action',
        'preventive_action',
        'pic',
        'relation_ship',
        'relation_ship2',
        'forwarded_to_unit',
        'unit_usaha',
        'source_type',
        'source_id',
        'source_item_idx',
        'priority',
        'status',
        'target_date',
        'actual_date',
        'evidence',
        'notes',
        'created_by',
        'updated_by',
        'closed_by',
        'closed_at',
        'close_note',
        'forwarded_filled_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'target_date' => 'date:Y-m-d',
        'actual_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'forwarded_filled_at' => 'datetime',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(AuditRecommendation::class, 'audit_recommendation_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AuditTask::class, 'audit_task_id');
    }
}
