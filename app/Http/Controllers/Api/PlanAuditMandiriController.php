<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use App\Models\PlanAuditMandiri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanAuditMandiriController extends Controller
{
    // Target realisasi audit mandiri per bulan, per jenis audit & jenis unit usaha.
    private const TARGETS = [
        'KAS' => ['H1' => 1],
        'SMH' => ['H1' => 1],
        'Sparepart' => ['H1' => 2, 'H2' => 4],
        'BPKB' => ['H1' => 1],
        'MT' => ['H1' => 1, 'H2' => 1],
    ];

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

    // GET /api/plan-audit-mandiri/pencapaian?tahun=&bulan=
    // Rekap realisasi vs target audit mandiri per jenis audit & jenis unit usaha (H1/H2).
    public function pencapaian(Request $request): JsonResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);

        // Peta suffix-3-huruf cabang → jenis unit usaha (H1/H2/WHS), memakai
        // konvensi yang sama dengan PlafonController (nama cabang berbeda format
        // antar tabel, dicocokkan lewat 3 huruf terakhir).
        $unitJenisBySuffix = DbUnitUsaha::query()
            ->get()
            ->filter(fn($u) => $u->unit_usaha && $u->jenis)
            ->keyBy(fn($u) => $this->suffix3($u->unit_usaha))
            ->map(fn($u) => strtoupper($u->jenis));

        $unitCountByJenis = $unitJenisBySuffix->countBy(fn($jenis) => $jenis);

        $rows = PlanAuditMandiri::query()
            ->where('jenis_pemeriksaan', 'audit_mandiri')
            ->whereYear('tgl_plan', $tahun)
            ->whereMonth('tgl_plan', $bulan)
            ->get(['jenis_audit', 'cabang']);

        $realisasi = [];
        foreach ($rows as $row) {
            $suffix = $this->suffix3($row->cabang);
            $unitJenis = $suffix ? ($unitJenisBySuffix[$suffix] ?? null) : null;
            if (!$unitJenis) {
                continue;
            }
            $jenisAudit = $row->jenis_audit;
            $realisasi[$jenisAudit][$unitJenis] = ($realisasi[$jenisAudit][$unitJenis] ?? 0) + 1;
        }

        $items = [];
        foreach (self::TARGETS as $jenisAudit => $targetPerUnitType) {
            foreach ($targetPerUnitType as $unitType => $targetPerUnit) {
                $unitCount = (int) ($unitCountByJenis[$unitType] ?? 0);
                $target = $unitCount * $targetPerUnit;
                $actual = (int) ($realisasi[$jenisAudit][$unitType] ?? 0);
                $capaian = $target > 0 ? round(($actual / $target) * 100, 1) : null;

                $items[] = [
                    'jenisAudit' => $jenisAudit,
                    'unitType' => $unitType,
                    'unitCount' => $unitCount,
                    'targetPerUnit' => $targetPerUnit,
                    'target' => $target,
                    'realisasi' => $actual,
                    'capaian' => $capaian,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'data' => $items,
        ]);
    }

    private function suffix3(?string $str): ?string
    {
        if (!$str) {
            return null;
        }
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $str));
        return strlen($clean) >= 3 ? substr($clean, -3) : null;
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
