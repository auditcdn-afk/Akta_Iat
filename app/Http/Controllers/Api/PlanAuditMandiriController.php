<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\PlanAuditMandiri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanAuditMandiriController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = PlanAuditMandiri::query()
            ->with('planAudit.crosscheck')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('no_plan', 'like', "%{$q}%")
                        ->orWhere('cabang', 'like', "%{$q}%")
                        ->orWhere('jenis_audit', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('jenis_pemeriksaan'), fn($q) => $q->where('jenis_pemeriksaan', $request->query('jenis_pemeriksaan')))
            ->latest()
            ->get()
            ->map(fn(PlanAuditMandiri $p) => $p->toAktaArray());

        return response()->json(['ok' => true, 'data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis_pemeriksaan' => ['required', 'string', 'in:audit_mandiri,sertijab'],
            'jenis_audit' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $tanggal = now();
        $tahun = (int) $tanggal->format('Y');

        // Nomor urut plan reset tiap tahun berbeda.
        $urutan = PlanAuditMandiri::query()->where('tahun_plan', $tahun)->count() + 1;

        $identitas = $data['jenis_pemeriksaan'] === 'sertijab' ? 'STB' : 'AM';
        $noPlan = sprintf(
            '%04d/%s/%s-%s',
            $urutan,
            $tanggal->format('d/m/Y'),
            $data['jenis_audit'],
            $identitas
        );

        // Buat plan_audits "bayangan" agar bisa langsung memakai form pemeriksaan
        // yang sama persis (Kas, SMH, Bank, dll) seperti menu Audit biasa.
        $shadowPlan = PlanAudit::query()->create([
            'no_spt' => $noPlan,
            'cabang' => $user?->unit_usaha,
            'cabang_area' => $user?->wilayah,
            'jenis_audit' => $data['jenis_audit'],
            'tgl_plan' => $tanggal->toDateString(),
            'status' => 'cabang_active',
            'is_mandiri' => true,
            'created_by' => $user?->username,
            'updated_by' => $user?->username,
        ]);

        $plan = PlanAuditMandiri::query()->create([
            ...$data,
            'plan_audit_id' => $shadowPlan->id,
            'no_plan' => $noPlan,
            'urutan' => $urutan,
            'tahun_plan' => $tahun,
            'cabang' => $user?->unit_usaha,
            'cabang_area' => $user?->wilayah,
            'tgl_plan' => $tanggal->toDateString(),
            'status' => 'draft',
            'created_by' => $user?->username,
            'updated_by' => $user?->username,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Plan audit mandiri berhasil dibuat.',
            'data' => $plan->toAktaArray(),
        ], 201);
    }

    public function destroy(PlanAuditMandiri $planAuditMandiri): JsonResponse
    {
        abort_if(
            $planAuditMandiri->planAudit?->crosscheck,
            422,
            'Plan ini sudah di-crosscheck oleh auditor dan tidak bisa dihapus lagi.'
        );

        $planAuditMandiri->planAudit()->delete();
        $planAuditMandiri->delete();

        return response()->json(['ok' => true, 'message' => 'Plan audit mandiri berhasil dihapus.']);
    }
}
