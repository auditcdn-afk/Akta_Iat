<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanHgp extends Model
{
    protected $table = 'pemeriksaan_hgp';

    protected $fillable = ['plan_audit_id', 'items_json', 'label_wo', 'created_by', 'updated_by'];

    protected $casts = ['items_json' => 'array'];

    /** Judul bawaan kolom yang menambah hitungan fisik. */
    public const LABEL_WO_BAWAAN = 'WO';

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class);
    }

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'items'       => $this->items_json ?? [],
            // Judul kolom WO bisa diganti per plan audit; kosong berarti bawaan.
            'labelWo'     => $this->label_wo ?: self::LABEL_WO_BAWAAN,
            'updatedAt'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
