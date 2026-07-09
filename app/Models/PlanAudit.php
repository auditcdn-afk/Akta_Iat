<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAudit extends Model
{
    protected $table = 'plan_audits';

    protected $fillable = [
        'no_spt',
        'cabang',
        'cabang_area',
        'jenis_audit',
        'tgl_plan',
        'tgl_mulai',
        'tgl_selesai',
        'kepala_tim',
        'tim',
        'status',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tim' => 'array',
        'tgl_plan' => 'date',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
    ];

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'noSpt' => $this->no_spt,
            'cabang' => $this->cabang,
            'cabangArea' => $this->cabang_area,
            'jenisAudit' => $this->jenis_audit,
            'tglPlan' => optional($this->tgl_plan)->format('Y-m-d'),
            'tglMulai' => optional($this->tgl_mulai)->format('Y-m-d'),
            'tglSelesai' => optional($this->tgl_selesai)->format('Y-m-d'),
            'kepalaTim' => $this->kepala_tim,
            'tim' => $this->tim ?: [],
            'status' => $this->status,
            'canMarkSelesai' => $this->canMarkSelesai(),
            'keterangan' => $this->keterangan,
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
            'logs' => $this->relationLoaded('logs')
                ? $this->logs->map->toAktaArray()->all()
                : [],
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AuditTask::class, 'plan_audit_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PlanAuditLog::class, 'plan_audit_id');
    }

    /** Catat satu entri riwayat status birokrasi. */
    public function recordLog(string $action, ?string $from, ?string $to, ?\App\Models\User $user = null, ?string $note = null): void
    {
        $this->logs()->create([
            'action'      => $action,
            'from_status' => $from,
            'to_status'   => $to,
            'actor'       => $user?->username,
            'actor_role'  => $user?->role,
            'note'        => $note,
        ]);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'plan_audit_id');
    }

    /**
     * Syarat cabang boleh menyatakan pemeriksaan selesai (status cabang_active -> done):
     * - BU Performance untuk unit usaha ini sudah ada.
     * - Birokrasi rekomendasi sudah dimulai dari cabang (step isi_rekomendasi berstatus done).
     */
    public function canMarkSelesai(): bool
    {
        if ($this->status !== 'cabang_active' || !$this->cabang) {
            return false;
        }

        $buOk = BuPerformance::query()->where('unit_usaha', $this->cabang)->exists();

        if (!$buOk) {
            return false;
        }

        return $this->recommendations()
            ->get()
            ->contains(fn(AuditRecommendation $r) => collect($r->steps ?: [])
                ->contains(fn($s) => ($s['step'] ?? '') === 'isi_rekomendasi' && ($s['status'] ?? '') === 'done'));
    }
}
