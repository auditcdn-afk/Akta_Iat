<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAuditMandiri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanAuditMandiriController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = PlanAuditMandiri::query()
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
            'cabang' => ['nullable', 'string', 'max:150'],
            'cabang_area' => ['nullable', 'string', 'max:150'],
            'tgl_plan' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $tglPlan = $data['tgl_plan'] ?? now()->toDateString();
        $tanggal = \Carbon\Carbon::parse($tglPlan);
        $tahun = (int) $tanggal->format('Y');

        // Nomor urut plan reset tiap tahun berbeda.
        $urutan = PlanAuditMandiri::query()->where('tahun_plan', $tahun)->count() + 1;

        $identitas = $data['jenis_pemeriksaan'] === 'sertijab' ? 'ST' : 'AM';
        $noPlan = sprintf(
            '%04d/%s/%s-%s',
            $urutan,
            $tanggal->format('d/m/Y'),
            strtoupper($data['jenis_audit']),
            $identitas
        );

        $plan = PlanAuditMandiri::query()->create([
            ...$data,
            'no_plan' => $noPlan,
            'urutan' => $urutan,
            'tahun_plan' => $tahun,
            'tgl_plan' => $tglPlan,
            'status' => 'draft',
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Plan audit mandiri berhasil dibuat.',
            'data' => $plan->toAktaArray(),
        ], 201);
    }

    public function destroy(PlanAuditMandiri $planAuditMandiri): JsonResponse
    {
        $planAuditMandiri->delete();

        return response()->json(['ok' => true, 'message' => 'Plan audit mandiri berhasil dihapus.']);
    }
}
