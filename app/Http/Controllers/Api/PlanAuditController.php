<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTask;
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
        'cabang_active'       => ['next' => 'done',                'roles' => ['auditor', 'manajer', 'admin', '__branch__']],
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
        $user = $request->user();
        $role = $user?->role;
        $onlyMine = !in_array($role, self::HO_ROLES, true);

        // Identitas auditor (display_name / name / username)
        $identities = array_values(array_filter([
            $user?->display_name,
            $user?->name,
            $user?->username,
        ]));

        $plans = PlanAudit::query()
            ->with(['logs' => fn($q) => $q->orderBy('created_at')])
            ->when($onlyMine && !empty($identities), function ($q) use ($identities) {
                // Cabang & role non-HO hanya melihat plan yang mereka terlibat sebagai tim
                $q->where(function ($sub) use ($identities) {
                    $sub->whereIn('kepala_tim', $identities)
                        ->orWhere(function ($json) use ($identities) {
                            foreach ($identities as $id) {
                                $json->orWhereJsonContains('tim', $id);
                            }
                        });
                });
            })
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

        $plan->recordLog('created', null, $plan->status, $request->user(), 'Plan audit dibuat');

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

        // Cabang hanya boleh menyatakan pemeriksaan selesai jika BU Performance dan
        // birokrasi rekomendasi (isi oleh cabang) sudah ada. HO tetap bebas tanpa syarat ini.
        if ($status === 'cabang_active' && $isBranch && !$plan->canMarkSelesai()) {
            return response()->json([
                'ok' => false,
                'message' => 'Belum bisa menyatakan selesai: pastikan BU Performance dan Rekomendasi (isi oleh cabang) sudah diisi.',
            ], 422);
        }

        $plan->status = $transition['next'];
        $plan->updated_by = $request->user()?->username;
        $plan->save();

        $plan->recordLog('advance', $status, $plan->status, $request->user(), 'Disetujui / dilanjutkan');

        // Saat plan berubah ke 'running', buat task untuk cabang agar muncul di Task mereka.
        if ($plan->status === 'running' && $plan->cabang) {
            $exists = AuditTask::query()
                ->where('plan_audit_id', $plan->id)
                ->where('assigned_to', $plan->cabang)
                ->exists();

            if (! $exists) {
                AuditTask::query()->create([
                    'plan_audit_id' => $plan->id,
                    'judul'         => trim(($plan->jenis_audit ?: 'Audit') . ' - ' . $plan->cabang),
                    'kategori'      => $plan->jenis_audit,
                    'assigned_to'   => $plan->cabang,
                    'priority'      => 'normal',
                    'status'        => 'todo',
                    'due_date'      => $plan->tgl_plan,
                    'catatan'       => 'Tugas cabang: konfirmasi kedatangan auditor untuk plan ' . $plan->no_spt,
                    'created_by'    => $request->user()?->username ?: 'system',
                    'updated_by'    => $request->user()?->username ?: 'system',
                ]);
            }
        }

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

        $alasan = trim((string) $request->input('alasan')) ?: null;

        $plan->status = 'draft';
        $plan->updated_by = $request->user()?->username;
        $plan->save();

        $plan->recordLog('reject', $status, 'draft', $request->user(), $alasan ? "Ditolak: {$alasan}" : 'Ditolak');

        $logger->write($request, 'PLAN_REJECT', 'plan_audits',
            "Plan {$plan->no_spt} ditolak dari {$status} → draft" . ($alasan ? " ({$alasan})" : ''), $request->user());

        return response()->json(['ok' => true, 'message' => 'Plan dikembalikan ke Draft.', 'data' => $plan->toAktaArray()]);
    }

    // ── Admin: reset status ke status tertentu ────────────────────────────────
    public function adminResetStatus(Request $request, PlanAudit $plan, ActivityLogger $logger): JsonResponse
    {
        $validStatuses = [
            'draft', 'pending_koordinator', 'pending_manajer', 'pending_coo',
            'scheduled', 'running', 'cabang_active', 'done',
        ];

        $request->validate([
            'status' => ['required', 'in:' . implode(',', $validStatuses)],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $plan->status;
        $newStatus = $request->input('status');
        $alasan    = trim((string) $request->input('alasan', '')) ?: 'Koreksi admin';
        $who       = $request->user()?->username ?? 'admin';

        $plan->status     = $newStatus;
        $plan->updated_by = $who;
        $plan->save();

        $plan->recordLog('reject', $oldStatus, $newStatus, $request->user(),
            "Koreksi admin: {$alasan}");

        $logger->write($request, 'PLAN_ADMIN_RESET', 'plan_audits',
            "Admin reset plan {$plan->no_spt}: {$oldStatus} → {$newStatus} ({$alasan})", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => "Status plan diubah dari [{$oldStatus}] ke [{$newStatus}].",
            'data'    => $plan->fresh()->toAktaArray(),
        ]);
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
