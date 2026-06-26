<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemeriksaanLampiran extends Model
{
    protected $table = 'pemeriksaan_lampiran';

    protected $fillable = ['plan_audit_id', 'files_json', 'merged_pdf', 'created_by', 'updated_by'];

    protected $casts = ['files_json' => 'array'];

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'planAuditId'=> $this->plan_audit_id,
            'files'      => $this->files_json ?? [],
            'mergedPdf'  => $this->merged_pdf,
            'updatedAt'  => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
