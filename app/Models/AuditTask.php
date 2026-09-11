<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AuditTask extends Model
{
    protected $table = 'audit_tasks';

    protected $fillable = [
        'plan_audit_id',
        'judul',
        'kategori',
        'assigned_to',
        'priority',
        'status',
        'started_at',
        'finished_at',
        'lampiran_path',
        'due_date',
        'completed_at',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getLampiranUrlAttribute(): ?string
    {
        return $this->lampiran_path ? Storage::url($this->lampiran_path) : null;
    }

    public function planAudit(): BelongsTo
    {
        return $this->belongsTo(PlanAudit::class, 'plan_audit_id');
    }

    /**
     * @param array<int,string>|null $unitUsahaWithBuPerformance Daftar unit_usaha yang sudah
     *        punya BuPerformance, dihitung sekali oleh pemanggil saat me-list banyak task,
     *        supaya canMarkSelesai() tidak menjalankan satu query per task (N+1).
     *        Null = query langsung, dipakai saat toAktaArray() untuk satu task saja.
     */
    public function toAktaArray(?array $unitUsahaWithBuPerformance = null): array
    {
        return [
            ...$this->toAktaListArray(),
            'priority' => $this->priority,
            'completedAt' => optional($this->completed_at)->toDateTimeString(),
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
            'updatedAt' => optional($this->updated_at)->toDateTimeString(),
            // Riwayat status hanya dipakai modal detail satu task (renderTimeline
            // di akta-task.js), tidak pernah oleh tabel daftar — jadi hanya ikut
            // kalau relasinya memang sudah di-eager-load oleh pemanggil
            // (?with_logs=1); modal mengambilnya sendiri lewat GET /api/plans/{plan}.
            'planAudit' => $this->planAudit
                ? $this->planAudit->toAktaRingkasArray($unitUsahaWithBuPerformance)
                : null,
        ];
    }

    /**
     * Bentuk task untuk endpoint DAFTAR: TANPA salinan data plan.
     *
     * Plan-nya dikirim sekali dalam peta tersendiri (lihat
     * AuditTaskController::index) lalu disambung kembali di browser. Dulu tiap
     * task membawa salinan penuh plan-nya, jadi plan dengan 3 petugas terkirim
     * 3 kali — pengulangan yang memakan ratusan KB pada data seukuran produksi.
     */
    public function toAktaListArray(): array
    {
        return [
            'id' => $this->id,
            'planAuditId' => $this->plan_audit_id,
            'judul' => $this->judul,
            'kategori' => $this->kategori,
            'assignedTo' => $this->assigned_to,
            'status' => $this->status,
            'startedAt' => optional($this->started_at)->format('Y-m-d'),
            'finishedAt' => optional($this->finished_at)->format('Y-m-d'),
            'lampiranUrl' => $this->lampiran_url,
            'lampiranName' => $this->lampiran_path ? basename($this->lampiran_path) : null,
            'dueDate' => optional($this->due_date)->format('Y-m-d'),
            'catatan' => $this->catatan,
        ];
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'audit_task_id');
    }

    public function pinjamanCabang(): HasMany
    {
        return $this->hasMany(PinjamanCabang::class, 'audit_task_id');
    }
}
