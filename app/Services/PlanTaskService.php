<?php

namespace App\Services;

use App\Models\AuditTask;
use App\Models\PlanAudit;
use Illuminate\Support\Collection;

/**
 * Menjembatani Plan Audit → Task auditor.
 *
 * Setiap plan menugaskan Kepala Tim + anggota Tim Audit (auditor). Service ini
 * memastikan tiap auditor yang ditugaskan memiliki satu task yang ter-link ke
 * plan, tanpa membuat duplikat.
 */
class PlanTaskService
{
    /**
     * Buat task yang belum ada untuk seluruh auditor pada satu plan.
     * Mengembalikan jumlah task baru yang dibuat.
     */
    public function syncPlan(PlanAudit $plan, ?string $actor = null): int
    {
        $assignees = $this->assignees($plan);

        if ($assignees->isEmpty()) {
            return 0;
        }

        $judul = trim(($plan->jenis_audit ?: 'Audit') . ' - ' . ($plan->cabang ?: '-'));
        $created = 0;

        foreach ($assignees as $assignee) {
            $exists = AuditTask::query()
                ->where('plan_audit_id', $plan->id)
                ->where('assigned_to', $assignee)
                ->exists();

            if ($exists) {
                continue;
            }

            AuditTask::query()->create([
                'plan_audit_id' => $plan->id,
                'judul'         => $judul,
                'kategori'      => $plan->jenis_audit,
                'assigned_to'   => $assignee,
                'priority'      => 'normal',
                'status'        => 'todo',
                'due_date'      => $plan->tgl_plan,
                'catatan'       => 'Dibuat otomatis dari Plan Audit ' . $plan->no_spt
                    . ($assignee === $plan->kepala_tim ? ' (Kepala Tim)' : ' (Tim Audit)'),
                'created_by'    => $actor ?: 'system',
                'updated_by'    => $actor ?: 'system',
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Sinkronkan seluruh plan yang ada (untuk backfill plan lama).
     * Mengembalikan total task baru yang dibuat.
     */
    public function syncAll(?string $actor = null): int
    {
        $total = 0;

        PlanAudit::query()
            ->orderBy('id')
            ->chunk(100, function (Collection $plans) use (&$total, $actor) {
                foreach ($plans as $plan) {
                    $total += $this->syncPlan($plan, $actor);
                }
            });

        return $total;
    }

    /**
     * Daftar auditor yang ditugaskan ke plan: Kepala Tim + anggota Tim Audit.
     */
    private function assignees(PlanAudit $plan): Collection
    {
        return collect([$plan->kepala_tim])
            ->merge($plan->tim ?: [])
            ->filter()
            ->unique()
            ->values();
    }
}
