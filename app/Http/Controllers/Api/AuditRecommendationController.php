<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditRecommendationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recommendations = AuditRecommendation::query()
            ->with(['planAudit', 'auditTask'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('judul', 'like', "%{$q}%")
                        ->orWhere('deskripsi', 'like', "%{$q}%")
                        ->orWhere('kategori', 'like', "%{$q}%")
                        ->orWhere('pic', 'like', "%{$q}%")
                        ->orWhereHas('planAudit', function ($planQuery) use ($q) {
                            $planQuery
                                ->where('no_spt', 'like', "%{$q}%")
                                ->orWhere('cabang', 'like', "%{$q}%");
                        })
                        ->orWhereHas('auditTask', function ($taskQuery) use ($q) {
                            $taskQuery->where('judul', 'like', "%{$q}%");
                        });
                });
            })
            ->when($request->filled('plan_audit_id'), function ($query) use ($request) {
                $query->where('plan_audit_id', $request->query('plan_audit_id'));
            })
            ->when($request->filled('audit_task_id'), function ($query) use ($request) {
                $query->where('audit_task_id', $request->query('audit_task_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('prioritas'), function ($query) use ($request) {
                $query->where('prioritas', $request->query('prioritas'));
            })
            ->latest()
            ->get()
            ->map(fn(AuditRecommendation $recommendation) => $recommendation->toAktaArray());

        return response()->json([
            'ok' => true,
            'data' => $recommendations,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? 'draft') === 'done' && empty($payload['tgl_selesai'])) {
            $payload['tgl_selesai'] = now()->toDateString();
        }

        $recommendation = AuditRecommendation::query()->create([
            ...$payload,
            'steps' => $payload['steps'] ?? $this->defaultSteps($request->user()?->username),
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write(
            $request,
            'RECOMMENDATION_CREATE',
            'audit_recommendations',
            'Membuat rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil dibuat.',
            'data' => $recommendation->toAktaArray(),
        ], 201);
    }

    public function show(AuditRecommendation $recommendation): JsonResponse
    {
        $recommendation->load(['planAudit', 'auditTask']);

        return response()->json([
            'ok' => true,
            'data' => $recommendation->toAktaArray(),
        ]);
    }

    public function update(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? $recommendation->status) === 'done' && empty($payload['tgl_selesai'])) {
            $payload['tgl_selesai'] = now()->toDateString();
        }

        if (($payload['status'] ?? $recommendation->status) !== 'done') {
            $payload['tgl_selesai'] = null;
        }

        $recommendation->fill([
            ...$payload,
            'steps' => $payload['steps'] ?? $recommendation->steps,
            'updated_by' => $request->user()?->username,
        ]);

        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write(
            $request,
            'RECOMMENDATION_UPDATE',
            'audit_recommendations',
            'Update rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil diperbarui.',
            'data' => $recommendation->toAktaArray(),
        ]);
    }

    public function destroy(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $judul = $recommendation->judul;

        $recommendation->delete();

        $logger->write(
            $request,
            'RECOMMENDATION_DELETE',
            'audit_recommendations',
            'Menghapus rekomendasi: ' . $judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil dihapus.',
        ]);
    }

    public function approve(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $recommendation->fill([
            'status' => 'approved',
            'approved_by' => $request->user()?->username,
            'approved_at' => now(),
            'updated_by' => $request->user()?->username,
        ]);

        $steps = $recommendation->steps ?: [];
        $steps[] = [
            'step' => 'approval',
            'status' => 'approved',
            'user' => $request->user()?->username,
            'role' => $request->user()?->role,
            'time' => now()->toDateTimeString(),
            'note' => 'Rekomendasi disetujui.',
        ];

        $recommendation->steps = $steps;
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write(
            $request,
            'RECOMMENDATION_APPROVE',
            'audit_recommendations',
            'Approve rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil disetujui.',
            'data' => $recommendation->toAktaArray(),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'audit_task_id' => ['nullable', 'integer', 'exists:audit_tasks,id'],
            'judul' => ['required', 'string', 'max:300'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'prioritas' => [
                'required',
                'string',
                Rule::in(['rendah', 'sedang', 'tinggi', 'urgent']),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['draft', 'open', 'in_progress', 'waiting_approval', 'approved', 'done', 'cancelled']),
            ],
            'pic' => ['nullable', 'string', 'max:150'],
            'deadline' => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date'],
            'steps' => ['nullable', 'array'],
        ]);
    }

    private function defaultSteps(?string $username): array
    {
        return [
            [
                'step' => 'created',
                'status' => 'done',
                'user' => $username,
                'time' => now()->toDateTimeString(),
                'note' => 'Rekomendasi dibuat.',
            ],
        ];
    }
}
