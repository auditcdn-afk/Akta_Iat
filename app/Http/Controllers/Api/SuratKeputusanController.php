<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\SuratKeputusan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SuratKeputusanController extends Controller
{
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    private array $approveManajerRoles = ['admin', 'manajer'];

    private array $approveAfdRoles = ['admin', 'afd'];

    public function index(Request $request): JsonResponse
    {
        $query = SuratKeputusan::query()
            ->with('planAudit')
            ->latest('id');

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId')
            ?? $request->query('planAuditId');

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('no_sk', 'like', "%{$keyword}%")
                    ->orWhere('no_spt', 'like', "%{$keyword}%")
                    ->orWhere('unit_usaha', 'like', "%{$keyword}%")
                    ->orWhere('jenis_audit', 'like', "%{$keyword}%")
                    ->orWhere('uploaded_by_name', 'like', "%{$keyword}%");
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(SuratKeputusan $suratKeputusan): JsonResponse
    {
        return response()->json([
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        if (!empty($data['plan_audit_id'])) {
            $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        }

        $data['status'] = $data['status'] ?? 'pending_manajer';
        $data['steps'] = $data['steps'] ?? [];

        $data['uploaded_by'] = $this->userIdentifier($request);
        $data['uploaded_by_name'] = $this->userDisplayName($request);
        $data['uploaded_at'] = now();

        $sk = SuratKeputusan::query()->create($data);

        return response()->json([
            'message' => 'SK berhasil dibuat.',
            'data' => $sk->load('planAudit'),
        ], 201);
    }

    public function update(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanWrite($request);

        if ($suratKeputusan->status === 'selesai' && !$this->canApproveAfd($request)) {
            abort(403, 'SK sudah selesai. Hanya admin/AFD yang boleh mengubah.');
        }

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (!empty($data['plan_audit_id'])) {
            $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        }

        if (($data['status'] ?? null) === 'pending_afd') {
            $this->ensureCanApproveManajer($request);
        }

        if (($data['status'] ?? null) === 'selesai') {
            $this->ensureCanApproveAfd($request);
        }

        $suratKeputusan->fill($data);
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil diperbarui.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function destroy(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanWrite($request);

        if ($suratKeputusan->status === 'selesai' && !$this->canApproveAfd($request)) {
            abort(403, 'SK selesai hanya boleh dihapus oleh admin/AFD.');
        }

        $suratKeputusan->delete();

        return response()->json([
            'ok' => true,
            'message' => 'SK berhasil dihapus.',
        ]);
    }

    public function approveManajer(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveManajer($request);

        if ($suratKeputusan->status !== 'pending_manajer') {
            abort(422, 'SK tidak berada pada status pending_manajer.');
        }

        $steps = $suratKeputusan->steps ?? [];

        $steps['manajer'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'approvedAt' => now()->toDateTimeString(),
        ];

        $suratKeputusan->status = 'pending_afd';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil disetujui manajer.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function approveAfd(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveAfd($request);

        if ($suratKeputusan->status !== 'pending_afd') {
            abort(422, 'SK tidak berada pada status pending_afd.');
        }

        $steps = $suratKeputusan->steps ?? [];

        $steps['afd'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'approvedAt' => now()->toDateTimeString(),
        ];

        $suratKeputusan->status = 'selesai';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil disetujui AFD.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    private function validatePayload(array $payload, bool $isCreate): array
    {
        return Validator::make($payload, [
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'no_spt' => ['nullable', 'string', 'max:80'],
            'unit_usaha' => ['nullable', 'string', 'max:150'],
            'jenis_audit' => ['nullable', 'string', 'max:80'],
            'no_sk' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:120'],
            'file_sk' => ['nullable', 'array'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['pending_manajer', 'pending_afd', 'selesai']),
            ],
            'steps' => ['nullable', 'array'],
        ])->validate();
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->all();

        $aliases = [
            'planId' => 'plan_audit_id',
            'plan_id' => 'plan_audit_id',
            'planAuditId' => 'plan_audit_id',
            'noSpt' => 'no_spt',
            'unitUsaha' => 'unit_usaha',
            'jenisAudit' => 'jenis_audit',
            'noSk' => 'no_sk',
            'fileSk' => 'file_sk',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function fillFromPlan(array &$data, int $planAuditId): void
    {
        $plan = PlanAudit::query()->find($planAuditId);

        if (!$plan) {
            return;
        }

        $data['no_spt'] = $data['no_spt']
            ?? $plan->getAttribute('no_spt')
            ?? $plan->getAttribute('noSpt')
            ?? null;

        $data['unit_usaha'] = $data['unit_usaha']
            ?? $plan->getAttribute('unit_usaha')
            ?? $plan->getAttribute('unitUsaha')
            ?? $plan->getAttribute('cabang')
            ?? null;

        $data['jenis_audit'] = $data['jenis_audit']
            ?? $plan->getAttribute('jenis_audit')
            ?? $plan->getAttribute('jenisAudit')
            ?? null;
    }

    private function ensureCanWrite(Request $request): void
    {
        abort_unless(
            in_array($this->role($request), $this->writeRoles, true),
            403,
            'Role tidak diizinkan mengubah SK.'
        );
    }

    private function ensureCanApproveManajer(Request $request): void
    {
        abort_unless(
            in_array($this->role($request), $this->approveManajerRoles, true),
            403,
            'Hanya admin/manajer yang boleh approve tahap manajer.'
        );
    }

    private function ensureCanApproveAfd(Request $request): void
    {
        abort_unless(
            $this->canApproveAfd($request),
            403,
            'Hanya admin/AFD yang boleh approve tahap AFD.'
        );
    }

    private function canApproveAfd(Request $request): bool
    {
        return in_array($this->role($request), $this->approveAfdRoles, true);
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function userIdentifier(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->username
            ?? $user->email
            ?? $user->id
            ?? null;
    }

    private function userDisplayName(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->display_name
            ?? $user->name
            ?? $user->username
            ?? $user->email
            ?? null;
    }
}
