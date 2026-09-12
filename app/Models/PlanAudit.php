<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_mandiri',
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

    /**
     * @param array<int,string>|null $unitUsahaWithBuPerformance Daftar unit_usaha yang
     *        sudah punya BuPerformance, dihitung sekali oleh pemanggil (mis. saat me-list
     *        banyak plan) agar canMarkSelesai() tidak query per-plan. Null = query langsung
     *        (dipakai saat toAktaArray() dipanggil untuk satu plan saja).
     */
    /**
     * Apakah $user termasuk tim plan ini (atau pembuatnya)?
     *
     * Tim audit disimpan sebagai NAMA (kepala_tim + tim[]), bukan id user,
     * sementara satu orang bisa tercatat dengan ejaan berbeda di kolom
     * display_name / name / username. Pencocokannya karena itu dilakukan atas
     * ketiganya sekaligus, dengan spasi dirapikan dan huruf besar/kecil
     * diabaikan — sama seperti filter "plan milik saya" di
     * PlanAuditController::index dan penugasan task di PlanTaskService.
     */
    public function dimilikiOleh(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $rapikan = fn ($nama) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $nama)));

        $identitas = collect([$user->display_name, $user->name, $user->username])
            ->map($rapikan)
            ->filter(fn ($n) => $n !== '');

        if ($identitas->isEmpty()) {
            return false;
        }

        if ($identitas->contains($rapikan($this->created_by))) {
            return true;
        }

        return collect([$this->kepala_tim])
            ->merge($this->tim ?: [])
            ->map($rapikan)
            ->filter(fn ($n) => $n !== '')
            ->intersect($identitas)
            ->isNotEmpty();
    }

    public function toAktaArray(?array $unitUsahaWithBuPerformance = null): array
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
            'isMandiri' => (bool) $this->is_mandiri,
            'canMarkSelesai' => $this->canMarkSelesai($unitUsahaWithBuPerformance),
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

    /**
     * Bentuk ringkas plan untuk endpoint DAFTAR task.
     *
     * Di daftar task, tiap task dulu membawa salinan penuh data plan-nya.
     * Satu plan dengan 3 petugas berarti data plan yang sama dikirim 3 kali —
     * pada data seukuran produksi itu 453 KB dari 1,18 MB balasan hanya berisi
     * pengulangan. Sekarang plannya dikirim SEKALI dalam peta tersendiri dan
     * task cukup menyebut planAuditId.
     */
    public function toAktaRingkasArray(?array $unitUsahaWithBuPerformance = null): array
    {
        return [
            'id' => $this->id,
            'noSpt' => $this->no_spt,
            'cabang' => $this->cabang,
            'cabangArea' => $this->cabang_area,
            'jenisAudit' => $this->jenis_audit,
            'tglPlan' => optional($this->tgl_plan)->format('Y-m-d'),
            'kepalaTim' => $this->kepala_tim,
            'tim' => $this->tim ?: [],
            'status' => $this->status,
            'canMarkSelesai' => $this->canMarkSelesai($unitUsahaWithBuPerformance),
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

    public function crosscheck(): HasOne
    {
        return $this->hasOne(PlanAuditMandiriCrosscheck::class, 'plan_audit_id');
    }

    public function realisasiDinas(): HasMany
    {
        return $this->hasMany(RealisasiDinas::class, 'plan_audit_id');
    }

    /**
     * Syarat boleh menyatakan pemeriksaan selesai (status cabang_active -> done):
     * - Plan sudah berada di cabang (status cabang_active, cabang sudah mulai).
     * - BU Performance untuk unit usaha ini sudah ada.
     */
    public function canMarkSelesai(?array $unitUsahaWithBuPerformance = null): bool
    {
        if ($this->status !== 'cabang_active' || !$this->cabang) {
            return false;
        }

        if ($unitUsahaWithBuPerformance !== null) {
            return in_array($this->cabang, $unitUsahaWithBuPerformance, true);
        }

        return BuPerformance::query()->where('unit_usaha', $this->cabang)->exists();
    }
}
