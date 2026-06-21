<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = PlanAudit::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('no_spt', 'like', "%{$q}%")
                        ->orWhere('cabang', 'like', "%{$q}%")
                        ->orWhere('cabang_area', 'like', "%{$q}%")
                        ->orWhere('jenis_audit', 'like', "%{$q}%")
                        ->orWhere('kepala_tim', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->latest()
            ->get()
            ->map(fn(PlanAudit $plan) => $plan->toAktaArray());

        return response()->json([
            'ok' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        $plan = PlanAudit::query()->create([
            ...$payload,
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        $logger->write(
            $request,
            'PLAN_CREATE',
            'plan_audits',
            'Membuat plan audit: ' . $plan->no_spt,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Plan audit berhasil dibuat.',
            'data' => $plan->toAktaArray(),
        ], 201);
    }

    public function show(PlanAudit $plan): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $plan->toAktaArray(),
        ]);
    }

    public function update(
        Request $request,
        PlanAudit $plan,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $this->validatedPayload($request);

        $plan->fill([
            ...$payload,
            'updated_by' => $request->user()?->username,
        ]);

        $plan->save();

        $logger->write(
            $request,
            'PLAN_UPDATE',
            'plan_audits',
            'Update plan audit: ' . $plan->no_spt,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Plan audit berhasil diperbarui.',
            'data' => $plan->toAktaArray(),
        ]);
    }

    public function destroy(
        Request $request,
        PlanAudit $plan,
        ActivityLogger $logger
    ): JsonResponse {
        $noSpt = $plan->no_spt;

        $plan->delete();

        $logger->write(
            $request,
            'PLAN_DELETE',
            'plan_audits',
            'Menghapus plan audit: ' . $noSpt,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Plan audit berhasil dihapus.',
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $payload = $request->validate([
            'no_spt' => ['nullable', 'string', 'max:100'],
            'cabang' => ['required', 'string', 'max:150'],
            'cabang_area' => ['nullable', 'string', 'max:150'],
            'jenis_audit' => ['required', 'string', 'max:100'],
            'tgl_mulai' => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date', 'after_or_equal:tgl_mulai'],
            'kepala_tim' => ['nullable', 'string', 'max:150'],
            'tim' => ['nullable', 'array'],
            'tim.*' => ['nullable', 'string', 'max:150'],
            'status' => [
                'required',
                'string',
                Rule::in(['draft', 'scheduled', 'running', 'done', 'cancelled']),
            ],
            'keterangan' => ['nullable', 'string'],
        ]);

        $payload['tim'] = array_values(array_filter($payload['tim'] ?? []));

        return $payload;
    }
}
