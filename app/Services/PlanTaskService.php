<?php

namespace App\Services;

use App\Models\AuditTask;
use App\Models\PlanAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
     * Jika plan sudah `running`, buat juga task untuk cabang.
     * Mengembalikan jumlah task baru yang dibuat.
     */
    public function syncPlan(PlanAudit $plan, ?string $actor = null): int
    {
        return $this->terkunci(fn() => $this->syncPlanTanpaKunci($plan, $actor));
    }

    private function syncPlanTanpaKunci(PlanAudit $plan, ?string $actor = null): int
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

        // Saat plan sudah running, pastikan task cabang ada (untuk backfill).
        if ($plan->status === 'running' && $plan->cabang) {
            $branchExists = AuditTask::query()
                ->where('plan_audit_id', $plan->id)
                ->where('assigned_to', $plan->cabang)
                ->exists();

            if (! $branchExists) {
                AuditTask::query()->create([
                    'plan_audit_id' => $plan->id,
                    'judul'         => $judul,
                    'kategori'      => $plan->jenis_audit,
                    'assigned_to'   => $plan->cabang,
                    'priority'      => 'normal',
                    'status'        => 'todo',
                    'due_date'      => $plan->tgl_plan,
                    'catatan'       => 'Tugas cabang: konfirmasi kedatangan auditor untuk plan ' . $plan->no_spt,
                    'created_by'    => $actor ?: 'system',
                    'updated_by'    => $actor ?: 'system',
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Sinkronkan seluruh plan yang ada (untuk backfill plan lama).
     * Mengembalikan total task baru yang dibuat.
     *
     * Dulu ini memanggil syncPlan() per plan — tiap plan menjalankan 2-5 query
     * (exists() per assignee + create()). Untuk riwayat plan yang sudah ribuan
     * baris (dijalankan tiap jam via cache di AuditTaskController::index()),
     * itu jadi ribuan query dalam satu request. Sekarang: SATU query untuk ambil
     * seluruh pasangan (plan_audit_id, assigned_to) yang sudah ada, dicocokkan
     * di memory, lalu task baru di-bulk-insert per batch — jumlah query tidak
     * lagi tergantung jumlah plan.
     */
    public function syncAll(?string $actor = null): int
    {
        return $this->terkunci(fn() => $this->syncAllTanpaKunci($actor));
    }

    private function syncAllTanpaKunci(?string $actor = null): int
    {
        $existing = AuditTask::query()
            ->get(['plan_audit_id', 'assigned_to'])
            ->map(fn($row) => $row->plan_audit_id . '|' . $row->assigned_to)
            ->flip();

        $now = now();
        $newRows = [];

        PlanAudit::query()
            ->orderBy('id')
            ->chunk(200, function (Collection $plans) use (&$newRows, &$existing, $actor, $now) {
                foreach ($plans as $plan) {
                    $judul = trim(($plan->jenis_audit ?: 'Audit') . ' - ' . ($plan->cabang ?: '-'));

                    foreach ($this->assignees($plan) as $assignee) {
                        $key = $plan->id . '|' . $assignee;
                        if (isset($existing[$key])) continue;
                        $existing[$key] = true;

                        $newRows[] = [
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
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ];
                    }

                    if ($plan->status === 'running' && $plan->cabang) {
                        $key = $plan->id . '|' . $plan->cabang;
                        if (!isset($existing[$key])) {
                            $existing[$key] = true;

                            $newRows[] = [
                                'plan_audit_id' => $plan->id,
                                'judul'         => $judul,
                                'kategori'      => $plan->jenis_audit,
                                'assigned_to'   => $plan->cabang,
                                'priority'      => 'normal',
                                'status'        => 'todo',
                                'due_date'      => $plan->tgl_plan,
                                'catatan'       => 'Tugas cabang: konfirmasi kedatangan auditor untuk plan ' . $plan->no_spt,
                                'created_by'    => $actor ?: 'system',
                                'updated_by'    => $actor ?: 'system',
                                'created_at'    => $now,
                                'updated_at'    => $now,
                            ];
                        }
                    }
                }
            });

        foreach (array_chunk($newRows, 500) as $batch) {
            AuditTask::query()->insert($batch);
        }

        // Rapikan juga plan yang sudah selesai/dibatalkan tapi task-nya masih
        // terbuka — termasuk plan lama dari sebelum aturan ini ada.
        AuditTask::query()
            ->where('status', '!=', 'done')
            ->whereIn(
                'plan_audit_id',
                PlanAudit::query()->whereIn('status', ['done', 'cancelled'])->select('id')
            )
            ->update([
                'status'       => 'done',
                'completed_at' => now(),
                'updated_by'   => $actor ?: 'system',
                'updated_at'   => now(),
            ]);

        return count($newRows);
    }

    /**
     * Tutup task yang masih terbuka pada plan yang sudah SELESAI atau DIBATALKAN.
     *
     * Halaman Task adalah tempat persinggahan pekerjaan yang masih berjalan,
     * dan task hanya tertutup kalau ada yang merekam pelaksanaannya. Plan yang
     * ditutup lewat jalur lain — mis. seluruh tahapnya dilewatkan admin, atau
     * dinyatakan selesai/dibatalkan dari halaman Plan Audit — tidak pernah
     * menyentuh task-nya, jadi barisnya menumpuk sebagai "Belum Dikerjakan" di
     * daftar auditor selamanya padahal tidak ada lagi yang perlu dikerjakan.
     *
     * Barisnya TIDAK dihapus, hanya ditandai selesai, jadi riwayatnya utuh dan
     * admin tetap bisa melihatnya lewat filter "Selesai".
     *
     * @return int jumlah task yang ditutup
     */
    public function tutupTaskPlanSelesai(PlanAudit $plan, ?string $actor = null): int
    {
        if (! in_array($plan->status, ['done', 'cancelled'], true)) {
            return 0;
        }

        return AuditTask::query()
            ->where('plan_audit_id', $plan->id)
            ->where('status', '!=', 'done')
            ->update([
                'status'       => 'done',
                'completed_at' => now(),
                'updated_by'   => $actor ?: 'system',
                'updated_at'   => now(),
            ]);
    }

    /**
     * Jalankan satu sinkronisasi dalam kunci global.
     *
     * Kedua jalur pembuatan task (syncPlan saat plan dibuat/diubah, syncAll saat
     * halaman Task dimuat) memeriksa dulu "apakah task-nya sudah ada", baru
     * menulis. Kalau dua permintaan berjalan bersamaan — mis. plan baru disimpan
     * tepat saat auditor lain membuka halaman Task — keduanya sama-sama melihat
     * "belum ada" lalu sama-sama menulis, dan plan itu muncul dua kali di daftar
     * Task padahal orangnya sama. Kunci ini membuat pemeriksaan dan penulisan
     * berjalan bergantian.
     *
     * Kalau kunci tidak didapat dalam beberapa detik (proses lain sedang
     * menyinkronkan hal yang sama), sinkronisasi ini dilewati saja — bukan
     * kegagalan: pekerjaannya sedang dikerjakan proses lain, dan menyimpan plan
     * tidak boleh ikut gagal hanya karena ini.
     */
    private function terkunci(callable $kerja): int
    {
        try {
            return Cache::lock('plan-tasks-sync', 60)->block(5, $kerja);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return 0;
        }
    }

    /**
     * Daftar auditor yang ditugaskan ke plan: Kepala Tim + anggota Tim Audit.
     *
     * Nama dirapikan dulu (spasi berlebih dibuang) dan dibandingkan tanpa
     * membedakan huruf besar/kecil. Tanpa itu, "Budi", "budi" dan "Budi "
     * dihitung tiga orang berbeda dan orang yang sama dapat tiga task pada
     * plan yang sama — di daftar Task terlihat seperti plan yang dobel.
     */
    private function assignees(PlanAudit $plan): Collection
    {
        return collect([$plan->kepala_tim])
            ->merge($plan->tim ?: [])
            ->map(fn($nama) => is_string($nama) ? trim(preg_replace('/\s+/', ' ', $nama)) : $nama)
            ->filter(fn($nama) => is_string($nama) && $nama !== '')
            ->unique(fn($nama) => mb_strtolower($nama))
            ->values();
    }
}
