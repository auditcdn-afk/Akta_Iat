<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemeriksaanHga extends Model
{
    protected $table = 'pemeriksaan_hga';

    protected $fillable = ['plan_audit_id', 'items_json', 'created_by', 'updated_by'];

    protected $casts = ['items_json' => 'array'];

    public function toAktaArray(): array
    {
        return [
            'id'          => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'items'       => $this->items_json ?? [],
            'updatedAt'   => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
