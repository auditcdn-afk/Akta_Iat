<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\PlanPenilaian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanPenilaianController extends Controller
{
    private const ALLOWED_ROLES = ['koordinator', 'manajer'];

    // Daftar penilaian untuk satu plan (semua role), agar frontend tahu mana yang sudah/belum.
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
        ]);

        $rows = PlanPenilaian::query()
            ->where('plan_audit_id', $data['plan_audit_id'])
            ->get();

        return response()->json([
            'data' => $rows->map(fn(PlanPenilaian $p) => $p->toAktaArray()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;

        abort_unless(
            in_array($role, self::ALLOWED_ROLES, true) || $role === 'admin',
            403,
            'Hanya koordinator/manajer yang boleh mengisi penilaian.'
        );

        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'catatan' => ['required', 'string', 'max:2000'],
        ]);

        $plan = PlanAudit::query()->findOrFail($data['plan_audit_id']);
        abort_unless($plan->status === 'done', 422, 'Penilaian hanya bisa diisi setelah plan berstatus selesai (done).');

        // Admin mengisi atas nama role yang relevan tidak jelas — batasi admin agar tetap perlu role eksplisit.
        abort_if($role === 'admin', 422, 'Gunakan akun koordinator/manajer untuk mengisi penilaian ini.');

        $exists = PlanPenilaian::query()
            ->where('plan_audit_id', $plan->id)
            ->where('role', $role)
            ->exists();

        abort_if($exists, 422, 'Penilaian untuk role Anda pada plan ini sudah pernah diisi.');

        $penilaian = PlanPenilaian::query()->create([
            'plan_audit_id' => $plan->id,
            'role' => $role,
            'username' => $user->username,
            'display_name' => $user->display_name ?? $user->name ?? $user->username,
            'tgl_pemeriksaan' => now(),
            'catatan' => $data['catatan'],
        ]);

        return response()->json([
            'message' => 'Penilaian berhasil disimpan.',
            'data' => $penilaian->toAktaArray(),
        ], 201);
    }
}
