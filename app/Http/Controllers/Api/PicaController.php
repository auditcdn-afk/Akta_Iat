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
    // Role cabang yang boleh mengisi kolom Problem Identification, Corrective Action, dll.
    private const BRANCH_ROLES = ['h1', 'h2', 'unit', 'bpk'];

    private array $writeRoles = ['admin', 'manajer', 'auditor', 'h1', 'h2', 'unit'];

    private array $closeRoles = ['admin', 'manajer'];

    // Role kantor pusat (HO) yang boleh melihat semua unit usaha.
    private const HO_ROLES = ['admin', 'manajer', 'auditor', 'koordinator', 'coo'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Pica::query()
            ->with(['recommendation', 'plan', 'task'])
            ->latest('id');

        // Role cabang (unit usaha, H1/H2/WHS) hanya boleh melihat PICA milik
        // plan dari unit usahanya sendiri.
        if (!in_array($user?->role, self::HO_ROLES, true)) {
            $query->whereHas('plan', fn($q) => $q->where('cabang', $user?->unit_usaha));
        }

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

        // Cabang melihat PICA milik unitnya ATAU PICA yang diteruskan ke unitnya
        $role = strtolower((string) ($request->user()?->role ?? ''));
        if (in_array($role, self::BRANCH_ROLES, true)) {
            $unitUsaha = $request->user()?->unit_usaha;
            if ($unitUsaha) {
                $hasForwardedCol = \Illuminate\Support\Facades\Schema::hasColumn('picas', 'forwarded_to_unit');
                $query->where(function ($q) use ($unitUsaha, $hasForwardedCol) {
                    $q->where('unit_usaha', $unitUsaha);
                    if ($hasForwardedCol) {
                        $q->orWhere('forwarded_to_unit', $unitUsaha);
                    }
                });
            }
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

        if (!empty($data['audit_recommendation_id'])) {
            $recommendation = AuditRecommendation::query()
                ->findOrFail($data['audit_recommendation_id']);

            $data['plan_audit_id'] = $recommendation->getAttribute('plan_audit_id')
                ?? $recommendation->getAttribute('plan_id')
                ?? null;

            $data['audit_task_id'] = $recommendation->getAttribute('audit_task_id')
                ?? $recommendation->getAttribute('task_id')
                ?? null;
        }

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
        $this->ensureCanWrite($request, $pica);

        if ($pica->status === 'closed' && !$this->canClose($request)) {
            abort(403, 'PICA sudah closed. Hanya admin/manajer yang boleh mengubah.');
        }

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (($data['status'] ?? null) === 'closed') {
            $this->ensureCanClose($request);
        }

        if (array_key_exists('audit_recommendation_id', $data) && !empty($data['audit_recommendation_id'])) {
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

        // Jika cabang menyimpan dan relation_ship sudah diisi → cari unit_usaha pihak terkait
        $role = $this->role($request);
        $relationShip = $data['relation_ship'] ?? $pica->relation_ship;

        $userUnit   = $request->user()?->unit_usaha;
        $isForwarded = $userUnit && (
            $pica->forwarded_to_unit === $userUnit ||
            ($pica->relation_ship && str_contains($pica->relation_ship, $userUnit)) ||
            ($pica->relation_ship2 && str_contains($pica->relation_ship2, $userUnit))
        );

        // Jika forwarded party yang menyimpan, tandai sudah diisi
        if ($isForwarded && !in_array($role, self::BRANCH_ROLES, true)) {
            $data['forwarded_filled_at'] = now();
        }

        if ((in_array($role, self::BRANCH_ROLES, true) || $isForwarded) && !empty($relationShip)) {
            // Parse nama dari format "Nama (unit_usaha)" atau cari user langsung
            $forwardedUnit = null;
            preg_match('/\(([^)]+)\)$/', $relationShip, $m);
            if (!empty($m[1])) {
                $forwardedUnit = trim($m[1]);
            } else {
                // Cari user berdasarkan nama
                $u = \App\Models\User::where('name', $relationShip)
                    ->orWhere('username', $relationShip)
                    ->first();
                $forwardedUnit = $u?->unit_usaha;
            }
            if ($forwardedUnit) $data['forwarded_to_unit'] = $forwardedUnit;

            // Auto progress jika masih open
            if ($pica->status === 'open' || ($data['status'] ?? $pica->status) === 'open') {
                $data['status'] = 'progress';
            }
        }

        // Coba simpan dengan forwarded_to_unit; jika kolom belum ada, simpan tanpa itu
        try {
            $pica->fill($data);
            $pica->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'forwarded_to_unit') || str_contains($e->getMessage(), 'Unknown column')) {
                unset($data['forwarded_to_unit']);
                $pica->fill($data);
                $pica->save();
            } else {
                throw $e;
            }
        }

        $forwarded = !empty($relationShip) && in_array($role, self::BRANCH_ROLES, true);
        $message   = $forwarded
            ? "PICA berhasil disimpan dan diteruskan ke: {$relationShip}."
            : 'PICA berhasil disimpan.';

        return response()->json([
            'message' => $message,
            'forwarded_to' => $forwarded ? $relationShip : null,
            ...$pica->load(['recommendation', 'plan', 'task'])->toArray(),
        ]);
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
                'nullable',
                'integer',
                'exists:audit_recommendations,id',
            ],
            'pica_no' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:200'],
            'problem' => ['nullable', 'string'],
            'current_condition' => ['nullable', 'string'],
            'problem_identification' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'preventive_action' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:150'],
            'relation_ship' => ['nullable', 'string', 'max:150'],
            'relation_ship2' => ['nullable', 'string', 'max:150'],
            'priority' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'target_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'evidence' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'unit_usaha' => ['nullable', 'string', 'max:150'],
            'forwarded_to_unit' => ['nullable', 'string', 'max:150'],
            'recheck_note' => ['nullable', 'string'],
            'recheck_deadline' => ['nullable', 'date'],
            'recheck_file' => ['nullable', 'string', 'max:255'],
            'recheck_at' => ['nullable', 'date'],
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
            'unitUsaha' => 'unit_usaha',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function ensureCanWrite(Request $request, ?Pica $pica = null): void
    {
        $canWrite = in_array($this->role($request), $this->writeRoles, true);

        // Also allow forwarded party (any role) to update the PICA
        if (!$canWrite && $pica) {
            $userUnit = $request->user()?->unit_usaha;
            $canWrite = $userUnit && $pica->forwarded_to_unit === $userUnit;
        }

        abort_unless($canWrite, 403, 'Role tidak diizinkan mengubah PICA.');
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
