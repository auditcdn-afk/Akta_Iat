<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PlanTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanAuditController extends Controller
{
    // Role-role kantor pusat (HO). Selain ini = role cabang.
    private const HO_ROLES = ['admin', 'manajer', 'auditor', 'koordinator', 'coo'];

    // Mesin status: dari status apa, siapa yang boleh maju, ke status apa.
    private const TRANSITIONS = [
        'draft'               => ['next' => 'pending_koordinator', 'roles' => ['auditor', 'admin']],
        'pending_koordinator' => ['next' => 'pending_manajer',     'roles' => ['koordinator', 'admin']],
        'pending_manajer'     => ['next' => 'pending_coo',         'roles' => ['manajer', 'admin']],
        'pending_coo'         => ['next' => 'scheduled',           'roles' => ['coo', 'admin']],
        'scheduled'           => ['next' => 'running',             'roles' => ['auditor', 'admin']],
        'running'             => ['next' => 'cabang_active',       'roles' => ['__branch__', 'admin']],
        'cabang_active'       => ['next' => 'done',                'roles' => ['auditor', 'manajer', 'admin']],
    ];

    // Status yang boleh ditolak (kembali ke draft).
    private const REJECTABLE = ['pending_koordinator', 'pending_manajer', 'pending_coo'];

    public function teamOptions(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->where('role', 'auditor')
            ->orderBy('wilayah')
            ->orderBy('unit_usaha')
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'username'    => $u->username,
                'name'        => $u->name,
                'displayName' => $u->display_name,
                'role'        => $u->role,
                'unitUsaha'   => $u->unit_usaha,
                'wilayah'     => $u->wilayah,
            ]);

        return response()->json(['ok' => true, 'data' => $users]);
    }

    public function index(Request $request): JsonResponse
    {
        $plans = PlanAudit::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('no_spt', 'like', "%{$q}%")
                        ->orWhere('cabang', 'like', "%{$q}%")
                        ->orWhere('jenis_audit', 'like', "%{$q}%")
                        ->orWhere('kepala_tim', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get()
            ->map(fn(PlanAudit $plan) => $plan->toAktaArray());

        return response()->json(['ok' => true, 'data' => $plans]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        if (empty($payload['no_spt'])) {
            $count = PlanAudit::query()->count() + 1;
            $payload['no_spt'] = sprintf('%04d/%s/SPT-IAT', $count, now()->format('d/m/Y'));
        }

        $payload['tgl_plan'] = $payload['tgl_plan'] ?? now()->toDateString();
        // Plan baru langsung menunggu persetujuan Koordinator (sesuai birokrasi:
        // manajer buat plan → koordinator approved → manajer approved → COO approved).
        $payload['status']   = 'pending_koordinator';

        $plan = PlanAudit::query()->create([
            ...$payload,
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        // Otomatis buat task untuk auditor yang dituju (kepala tim + anggota tim),
        // sehingga plan langsung muncul di Task auditor bersangkutan.
        app(PlanTaskService::class)->syncPlan($plan, $request->user()?->username);

        $logger->write($request, 'PLAN_CREATE', 'plan_audits', 'Membuat plan audit: ' . $plan->no_spt, $request->user());

        return response()->json(['ok' => true, 'message' => 'Plan audit berhasil dibuat. Task untuk auditor telah dibuat otomatis.', 'data' => $plan->toAktaArray()], 201);
    }

    public function show(PlanAudit $plan): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $plan->toAktaArray()]);
    }

    public function update(Request $request, PlanAudit $plan, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        $plan->fill([...$payload, 'updated_by' => $request->user()?->username]);
        $plan->save();

        // Auditor bisa berubah saat diedit; buatkan task untuk yang baru ditambah.
        app(PlanTaskService::class)->syncPlan($plan->fresh(), $request->user()?->username);

        $logger->write($request, 'PLAN_UPDATE', 'plan_audits', 'Update plan audit: ' . $plan->no_spt, $request->user());

        return response()->json(['ok' => true, 'message' => 'Plan audit berhasil diperbarui.', 'data' => $plan->toAktaArray()]);
    }

    public function destroy(Request $request, PlanAudit $plan, ActivityLogger $logger): JsonResponse
    {
        $noSpt = $plan->no_spt;
        $plan->delete();

        $logger->write($request, 'PLAN_DELETE', 'plan_audits', 'Menghapus plan audit: ' . $noSpt, $request->user());

        return response()->json(['ok' => true, 'message' => 'Plan audit berhasil dihapus.']);
    }

    // ── Advance: maju ke tahap berikutnya ────────────────────────────────────
    public function advance(Request $request, PlanAudit $plan, ActivityLogger $logger): JsonResponse
    {
        $role = $request->user()?->role;
        $status = $plan->status;

        $transition = self::TRANSITIONS[$status] ?? null;

        if (! $transition) {
            return response()->json(['ok' => false, 'message' => 'Status ini tidak bisa dilanjutkan.'], 422);
        }

        $allowed = $transition['roles'];
        $isBranch = ! in_array($role, self::HO_ROLES);

        $canAdvance = in_array($role, $allowed) || (in_array('__branch__', $allowed) && $isBranch);

        if (! $canAdvance) {
            return response()->json(['ok' => false, 'message' => 'Role kamu tidak bisa melakukan aksi ini.'], 403);
        }

        $plan->status = $transition['next'];
        $plan->updated_by = $request->user()?->username;
        $plan->save();

        $logger->write($request, 'PLAN_ADVANCE', 'plan_audits',
            "Plan {$plan->no_spt}: {$status} → {$plan->status}", $request->user());

        return response()->json(['ok' => true, 'message' => 'Status plan berhasil diperbarui.', 'data' => $plan->toAktaArray()]);
    }

    // ── Reject: tolak, kembalikan ke draft ───────────────────────────────────
    public function reject(Request $request, PlanAudit $plan, ActivityLogger $logger): JsonResponse
    {
        $role = $request->user()?->role;
        $status = $plan->status;

        if (! in_array($status, self::REJECTABLE)) {
            return response()->json(['ok' => false, 'message' => 'Status ini tidak bisa ditolak.'], 422);
        }

        // Hanya pemilik tahap tsb atau admin yang bisa tolak
        $rejectAllowed = [
            'pending_koordinator' => ['koordinator', 'admin'],
            'pending_manajer'     => ['manajer', 'admin'],
            'pending_coo'         => ['coo', 'admin'],
        ];

        if (! in_array($role, $rejectAllowed[$status] ?? [])) {
            return response()->json(['ok' => false, 'message' => 'Role kamu tidak bisa menolak.'], 403);
        }

        $plan->status = 'draft';
        $plan->updated_by = $request->user()?->username;
        $plan->save();

        $logger->write($request, 'PLAN_REJECT', 'plan_audits',
            "Plan {$plan->no_spt} ditolak dari {$status} → draft", $request->user());

        return response()->json(['ok' => true, 'message' => 'Plan dikembalikan ke Draft.', 'data' => $plan->toAktaArray()]);
    }

    private function validatedPayload(Request $request): array
    {
        $allStatuses = array_merge(
            ['draft', 'scheduled', 'running', 'done', 'cancelled'],
            ['pending_koordinator', 'pending_manajer', 'pending_coo', 'cabang_active']
        );

        $payload = $request->validate([
            'no_spt'      => ['nullable', 'string', 'max:100'],
            'cabang'      => ['required', 'string', 'max:150'],
            'cabang_area' => ['nullable', 'string', 'max:150'],
            'jenis_audit' => ['required', 'string', 'max:150'],
            'tgl_plan'    => ['nullable', 'date'],
            'tgl_mulai'   => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date', 'after_or_equal:tgl_mulai'],
            'kepala_tim'  => ['nullable', 'string', 'max:150'],
            'tim'         => ['nullable', 'array'],
            'tim.*'       => ['nullable', 'string', 'max:150'],
            'status'      => ['nullable', 'string', Rule::in($allStatuses)],
            'keterangan'  => ['nullable', 'string'],
        ]);

        $payload['tim'] = array_values(array_filter($payload['tim'] ?? []));

        if (! array_key_exists('status', $payload) || $payload['status'] === null) {
            unset($payload['status']);
        }
        if (! array_key_exists('tgl_plan', $payload) || $payload['tgl_plan'] === null) {
            unset($payload['tgl_plan']);
        }

        return $payload;
    }
}
