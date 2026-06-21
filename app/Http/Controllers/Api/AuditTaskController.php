<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTask;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = AuditTask::query()
            ->with('planAudit')
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
