<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\PlanAuditMandiriCrosscheck;
use App\Services\BirokrasiResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanAuditMandiriCrosscheckController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
        ]);

        $row = PlanAuditMandiriCrosscheck::query()
            ->where('plan_audit_id', $data['plan_audit_id'])
            ->first();

        return response()->json(['data' => $row?->toAktaArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && in_array($user->role, ['auditor', 'admin'], true),
            403,
            'Hanya auditor yang boleh melakukan crosscheck.'
        );

        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'hasil' => ['required', 'string', Rule::in(['ok', 'not_ok', 'selisih'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'rekomendasi' => ['required_if:hasil,selisih', 'array'],
            'rekomendasi.judul' => ['required_if:hasil,selisih', 'string', 'max:300'],
            'rekomendasi.deskripsi' => ['nullable', 'string'],
            'rekomendasi.kategori' => ['nullable', 'string', 'max:100'],
            'rekomendasi.prioritas' => ['nullable', 'string', Rule::in(['rendah', 'sedang', 'tinggi', 'urgent'])],
            'rekomendasi.pic' => ['nullable', 'string', 'max:150'],
            'rekomendasi.deadline' => ['nullable', 'date'],
        ]);

        $plan = PlanAudit::query()->findOrFail($data['plan_audit_id']);
        abort_unless($plan->is_mandiri, 422, 'Crosscheck hanya berlaku untuk plan Audit Mandiri/Sertijab.');

        $existing = PlanAuditMandiriCrosscheck::query()->where('plan_audit_id', $plan->id)->first();
        abort_if(
            $existing && $user->role !== 'admin',
            403,
            'Plan ini sudah pernah di-crosscheck. Hanya admin yang boleh mengubah hasilnya.'
        );

        $crosscheck = PlanAuditMandiriCrosscheck::query()->updateOrCreate(
            ['plan_audit_id' => $plan->id],
            [
                'hasil' => $data['hasil'],
                'catatan' => $data['catatan'] ?? null,
                'username' => $user->username,
                'display_name' => $user->display_name ?? $user->name ?? $user->username,
            ]
        );

        $recommendation = null;
        if ($data['hasil'] === 'selisih') {
            $rek = $data['rekomendasi'];
            $recommendation = AuditRecommendation::query()->create([
                'plan_audit_id' => $plan->id,
                'judul' => $rek['judul'],
                'deskripsi' => $rek['deskripsi'] ?? null,
                'kategori' => $rek['kategori'] ?? null,
                'prioritas' => $rek['prioritas'] ?? 'sedang',
                'status' => 'open',
                'pic' => $rek['pic'] ?? null,
                'deadline' => $rek['deadline'] ?? null,
                // Alur birokrasi rekomendasi disamakan dengan plan Audit biasa,
                // mengikuti unit usaha (cabang) plan Audit Mandiri ini.
                'steps' => BirokrasiResolver::buildSteps($plan->cabang ?? '', $user->username),
                'created_by' => $user->username,
                'updated_by' => $user->username,
            ]);
        }

        return response()->json([
            'message' => 'Crosscheck berhasil disimpan.',
            'data' => $crosscheck->toAktaArray(),
            'recommendation' => $recommendation?->toAktaArray(),
        ], 201);
    }
}
