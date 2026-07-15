<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTask;
use App\Services\ActivityLogger;
use App\Services\PlanTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuditTaskController extends Controller
{
    // Role kantor pusat (HO) yang boleh melihat semua task.
    private const HO_OVERSIGHT = ['admin', 'manajer', 'koordinator', 'coo'];

    // Role approval: hanya melihat task yang plannya menunggu persetujuan mereka.
    private const APPROVAL_STAGE = [
        'koordinator' => 'pending_koordinator',
        'manajer'     => 'pending_manajer',
        'coo'         => 'pending_coo',
    ];

    public function index(Request $request, PlanTaskService $planTasks): JsonResponse
    {
        // Backfill otomatis: pastikan setiap plan (lama & baru) sudah punya task
        // untuk auditor yang ditugaskan, sehingga langsung muncul di sini.
        // syncPlan() sudah dipanggil tepat saat plan dibuat/berubah status (lihat
        // PlanAuditController), jadi syncAll() di sini hanya perlu untuk backfill
        // plan lama dari sebelum mekanisme ini ada. syncAll() men-scan SELURUH
        // tabel plan_audits + beberapa query per plan — di-cache 1 jam supaya
        // tidak diulang di setiap request (sebelumnya berjalan penuh pada SETIAP
        // load halaman Task, makin berat seiring bertambahnya riwayat audit).
        Cache::remember('plan_tasks_synced_at', 3600, function () use ($planTasks, $request) {
            $planTasks->syncAll($request->user()?->username);
            return true;
        });

        $user = $request->user();
        $role = $user?->role;

        // Auditor & role cabang hanya melihat task yang ditugaskan kepada dirinya.
        // Admin/manajer/koordinator/COO melihat seluruh task (pengawasan).
        $onlyMine = ! in_array($role, self::HO_OVERSIGHT, true);

        // Untuk branch user (bukan HO, bukan approval), sertakan unit_usaha agar
        // task cabang (assigned_to = cabang name) bisa cocok.
        $identities = array_values(array_filter([
            $user?->display_name,
            $user?->name,
            $user?->username,
            (! in_array($role, self::HO_OVERSIGHT, true) && ! isset(self::APPROVAL_STAGE[$role]))
                ? $user?->unit_usaha
                : null,
        ]));

        // Task hanya tempat persinggahan kegiatan:
        // - Role approval (koordinator/manajer/coo) hanya melihat task yang
        //   plannya sedang menunggu persetujuan mereka. Setelah disetujui,
        //   plan pindah tahap dan task otomatis hilang dari daftar mereka.
        // - Selain itu, task yang sudah selesai (status done) selalu disembunyikan.
        $approvalStage = self::APPROVAL_STAGE[$role] ?? null;

        $tasks = AuditTask::query()
            ->with(['planAudit.logs' => fn($q) => $q->orderBy('created_at')])
            ->when($approvalStage, function ($query) use ($approvalStage) {
                $query->whereHas('planAudit', fn($q) => $q->where('status', $approvalStage));
            })
            ->when(! $approvalStage, function ($query) use ($onlyMine, $identities, $role) {
                // Auditor/cabang: hanya task miliknya. Semua role non-approval:
                // sembunyikan task yang sudah selesai (transit), kecuali admin
                // yang butuh akses untuk koreksi status.
                if ($onlyMine && ! empty($identities)) {
                    $query->whereIn('assigned_to', $identities);
                }
                if ($role !== 'admin') {
                    $query->where('status', '!=', 'done');
                }
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('judul', 'like', "%{$q}%")
                        ->orWhere('kategori', 'like', "%{$q}%")
                        ->orWhere('assigned_to', 'like', "%{$q}%")
                        ->orWhere('catatan', 'like', "%{$q}%")
                        ->orWhereHas('planAudit', function ($planQuery) use ($q) {
                            $planQuery
                                ->where('no_spt', 'like', "%{$q}%")
                                ->orWhere('cabang', 'like', "%{$q}%");
                        });
                });
            })
            ->when($request->filled('plan_audit_id'), function ($query) use ($request) {
                $query->where('plan_audit_id', $request->query('plan_audit_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('priority'), function ($query) use ($request) {
                $query->where('priority', $request->query('priority'));
            })
            ->latest()
            ->get()
            ->map(fn(AuditTask $task) => $task->toAktaArray());

        return response()->json([
            'ok' => true,
            'data' => $tasks,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? 'todo') === 'done') {
            $payload['completed_at'] = now();
        }

        $task = AuditTask::query()->create([
            ...$payload,
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        $task->load('planAudit');

        $logger->write(
            $request,
            'TASK_CREATE',
            'audit_tasks',
            'Membuat task audit: ' . $task->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Task audit berhasil dibuat.',
            'data' => $task->toAktaArray(),
        ], 201);
    }

    public function show(AuditTask $task): JsonResponse
    {
        $task->load('planAudit');

        return response()->json([
            'ok' => true,
            'data' => $task->toAktaArray(),
        ]);
    }

    public function update(
        Request $request,
        AuditTask $task,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? $task->status) === 'done' && ! $task->completed_at) {
            $payload['completed_at'] = now();
        }

        if (($payload['status'] ?? $task->status) !== 'done') {
            $payload['completed_at'] = null;
        }

        $task->fill([
            ...$payload,
            'updated_by' => $request->user()?->username,
        ]);

        $task->save();
        $task->load('planAudit');

        $logger->write(
            $request,
            'TASK_UPDATE',
            'audit_tasks',
            'Update task audit: ' . $task->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Task audit berhasil diperbarui.',
            'data' => $task->toAktaArray(),
        ]);
    }

    public function destroy(
        Request $request,
        AuditTask $task,
        ActivityLogger $logger
    ): JsonResponse {
        $judul = $task->judul;

        $task->delete();

        $logger->write(
            $request,
            'TASK_DELETE',
            'audit_tasks',
            'Menghapus task audit: ' . $judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Task audit berhasil dihapus.',
        ]);
    }

    /**
     * Auditor merekam pelaksanaan audit: waktu Mulai, Selesai, dan Lampiran.
     * Mulai & Selesai wajib; Lampiran opsional. Task otomatis jadi 'done'.
     */
    public function execute(
        Request $request,
        AuditTask $task,
        ActivityLogger $logger
    ): JsonResponse {
        $user = $request->user();

        // Auditor hanya boleh mengerjakan task miliknya; admin/manajer bebas.
        $isOversight = in_array($user?->role, ['admin', 'manajer'], true);
        $identities = array_filter([$user?->display_name, $user?->name, $user?->username]);

        if (! $isOversight && ! in_array($task->assigned_to, $identities, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Task ini bukan ditugaskan kepada Anda.',
            ], 403);
        }

        $data = $request->validate([
            'started_at'  => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at'],
            'lampiran'    => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx'],
        ], [
            'started_at.required'  => 'Tanggal Mulai Audit wajib diisi.',
            'finished_at.required' => 'Tanggal Selesai Audit wajib diisi.',
            'finished_at.after_or_equal' => 'Tanggal Selesai tidak boleh sebelum Tanggal Mulai.',
        ]);

        $task->started_at  = $data['started_at'];
        $task->finished_at = $data['finished_at'];
        $task->status      = 'done';
        $task->completed_at = $data['finished_at'];

        if ($request->hasFile('lampiran')) {
            // Hapus lampiran lama bila ada
            if ($task->lampiran_path) {
                Storage::disk('public')->delete($task->lampiran_path);
            }
            $task->lampiran_path = $request->file('lampiran')->store('lampiran-audit', 'public');
        }

        $task->updated_by = $user?->username;
        $task->save();
        $task->load('planAudit');

        // Catat di riwayat birokrasi plan
        if ($task->planAudit) {
            $task->planAudit->recordLog(
                'execute',
                $task->planAudit->status,
                $task->planAudit->status,
                $user,
                "Auditor merekam pelaksanaan: {$data['started_at']} s/d {$data['finished_at']}"
            );
        }

        $logger->write(
            $request,
            'TASK_EXECUTE',
            'audit_tasks',
            'Auditor menyelesaikan audit: ' . $task->judul,
            $user
        );

        return response()->json([
            'ok' => true,
            'message' => 'Pelaksanaan audit berhasil disimpan.',
            'data' => $task->toAktaArray(),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'judul' => ['required', 'string', 'max:200'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'string', 'max:150'],
            'priority' => [
                'required',
                'string',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['todo', 'in_progress', 'review', 'done', 'cancelled']),
            ],
            'due_date' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);
    }
}
