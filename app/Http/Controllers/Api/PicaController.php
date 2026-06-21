<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Models\Pica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PicaController extends Controller
{
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    private array $closeRoles = ['admin', 'manajer'];

    public function index(Request $request): JsonResponse
    {
        $query = Pica::query()
            ->with(['recommendation', 'plan', 'task'])
            ->latest('id');

        $recommendationId = $request->query('audit_recommendation_id')
            ?? $request->query('recommendation_id')
            ?? $request->query('recommendationId');

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId');

        $auditTaskId = $request->query('audit_task_id')
            ?? $request->query('task_id')
            ?? $request->query('taskId');

        if ($recommendationId) {
            $query->where('audit_recommendation_id', $recommendationId);
        }

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        if ($auditTaskId) {
            $query->where('audit_task_id', $auditTaskId);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority') && $request->query('priority') !== 'all') {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('prioritas') && $request->query('prioritas') !== 'all') {
            $query->where('priority', $request->query('prioritas'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('pica_no', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('problem', 'like', "%{$keyword}%")
                    ->orWhere('root_cause', 'like', "%{$keyword}%")
                    ->orWhere('corrective_action', 'like', "%{$keyword}%")
                    ->orWhere('preventive_action', 'like', "%{$keyword}%")
                    ->orWhere('pic', 'like', "%{$keyword}%")
                    ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        return response()->json($query->get());
    }

    public function show(Pica $pica): JsonResponse
    {
        return response()->json(
            $pica->load(['recommendation', 'plan', 'task'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        $recommendation = AuditRecommendation::query()
            ->findOrFail($data['audit_recommendation_id']);

        $data['plan_audit_id'] = $recommendation->getAttribute('plan_audit_id')
            ?? $recommendation->getAttribute('plan_id')
            ?? null;

        $data['audit_task_id'] = $recommendation->getAttribute('audit_task_id')
            ?? $recommendation->getAttribute('task_id')
            ?? null;

        $data['created_by'] = $this->userName($request);
        $data['status'] = $data['status'] ?? 'open';
        $data['priority'] = $data['priority'] ?? 'sedang';

        $pica = Pica::query()->create($data);

        if (!$pica->pica_no) {
            $pica->pica_no = 'PICA-' . now()->format('Ymd') . '-' . str_pad((string) $pica->id, 4, '0', STR_PAD_LEFT);
            $pica->save();
        }

        return response()->json(
            $pica->load(['recommendation', 'plan', 'task']),
            201
        );
    }

    public function update(Request $request, Pica $pica): JsonResponse
    {
        $this->ensureCanWrite($request);

        if ($pica->status === 'closed' && !$this->canClose($request)) {
            abort(403, 'PICA sudah closed. Hanya admin/manajer yang boleh mengubah.');
        }

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (($data['status'] ?? null) === 'closed') {
            $this->ensureCanClose($request);
        }

        if (array_key_exists('audit_recommendation_id', $data)) {
            $recommendation = AuditRecommendation::query()
                ->findOrFail($data['audit_recommendation_id']);

            $data['plan_audit_id'] = $recommendation->getAttribute('plan_audit_id')
                ?? $recommendation->getAttribute('plan_id')
                ?? null;

            $data['audit_task_id'] = $recommendation->getAttribute('audit_task_id')
                ?? $recommendation->getAttribute('task_id')
                ?? null;
        }

        $data['updated_by'] = $this->userName($request);

        $pica->fill($data);
        $pica->save();

        return response()->json(
            $pica->load(['recommendation', 'plan', 'task'])
        );
    }

    public function destroy(Request $request, Pica $pica): JsonResponse
    {
        if ($pica->status === 'closed') {
            $this->ensureCanClose($request);
        } else {
            $this->ensureCanWrite($request);
        }

        $pica->delete();

        return response()->json([
            'ok' => true,
            'message' => 'PICA berhasil dihapus.',
        ]);
    }

    public function close(Request $request, Pica $pica): JsonResponse
    {
        $this->ensureCanClose($request);

        $payload = $this->normalizePayload($request);

        $data = Validator::make($payload, [
            'actual_date' => ['nullable', 'date'],
            'close_note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $pica->status = 'closed';
        $pica->actual_date = $data['actual_date'] ?? now()->toDateString();
        $pica->closed_by = $this->userName($request);
        $pica->closed_at = now();
        $pica->close_note = $data['close_note'] ?? $data['notes'] ?? null;
        $pica->updated_by = $this->userName($request);
        $pica->save();

        return response()->json(
            $pica->load(['recommendation', 'plan', 'task'])
        );
    }

    private function validatePayload(array $payload, bool $isCreate): array
    {
        $rules = [
            'audit_recommendation_id' => [
                $isCreate ? 'required' : 'sometimes',
                'integer',
                'exists:audit_recommendations,id',
            ],
            'pica_no' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:200'],
            'problem' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'preventive_action' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:150'],
            'priority' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'target_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'evidence' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];

        return Validator::make($payload, $rules)->validate();
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->all();

        $aliases = [
            'recommendationId' => 'audit_recommendation_id',
            'recommendation_id' => 'audit_recommendation_id',
            'planId' => 'plan_audit_id',
            'plan_id' => 'plan_audit_id',
            'taskId' => 'audit_task_id',
            'task_id' => 'audit_task_id',
            'picaNo' => 'pica_no',
            'rootCause' => 'root_cause',
            'correctiveAction' => 'corrective_action',
            'preventiveAction' => 'preventive_action',
            'targetDate' => 'target_date',
            'actualDate' => 'actual_date',
            'closeNote' => 'close_note',
            'prioritas' => 'priority',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function ensureCanWrite(Request $request): void
    {
        abort_unless(
            in_array($this->role($request), $this->writeRoles, true),
            403,
            'Role tidak diizinkan mengubah PICA.'
        );
    }

    private function ensureCanClose(Request $request): void
    {
        abort_unless(
            $this->canClose($request),
            403,
            'Hanya admin/manajer yang boleh close PICA.'
        );
    }

    private function canClose(Request $request): bool
    {
        return in_array($this->role($request), $this->closeRoles, true);
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function userName(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->username
            ?? $user->display_name
            ?? $user->name
            ?? $user->email
            ?? null;
    }
}
