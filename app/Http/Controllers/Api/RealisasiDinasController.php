<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\RealisasiDinas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RealisasiDinasController extends Controller
{
    private const CREATE_ROLES = ['admin', 'manajer', 'auditor', 'koordinator'];

    public const JENIS_PENGELUARAN = [
        'Biaya Perobatan',
        'Transportasi Laut',
        'Akomodasi',
        'Transportasi Darat',
        'Konsumsi',
        'Pramenu',
        'Transportasi Udara',
        'Laundry',
        'Komunikasi',
        'Lain-lain',
    ];

    // ── GET /api/realisasi-dinas?plan_audit_id=&jenis_pengeluaran=&tahun=&bulan= ──

    public function index(Request $request): JsonResponse
    {
        $rows = RealisasiDinas::query()
            ->with('planAudit')
            ->when($request->filled('plan_audit_id'), fn($q) => $q->where('plan_audit_id', $request->query('plan_audit_id')))
            ->when($request->filled('jenis_pengeluaran'), fn($q) => $q->where('jenis_pengeluaran', $request->query('jenis_pengeluaran')))
            ->when($request->filled('tahun'), fn($q) => $q->whereYear('tanggal_settlement', $request->query('tahun')))
            ->when($request->filled('bulan'), fn($q) => $q->whereMonth('tanggal_settlement', $request->query('bulan')))
            ->orderByDesc('tanggal_settlement')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map->toAktaArray(),
            'jenisPengeluaranOptions' => self::JENIS_PENGELUARAN,
        ]);
    }

    // GET /api/realisasi-dinas/plan-options — plan yang sudah "done", untuk dropdown form.

    public function planOptions(Request $request): JsonResponse
    {
        $plans = PlanAudit::query()
            ->where('status', 'done')
            ->orderByDesc('tgl_selesai')
            ->limit(200)
            ->get()
            ->map(fn(PlanAudit $p) => [
                'id' => $p->id,
                'cabang' => $p->cabang,
                'noSpt' => $p->no_spt,
                'tglSelesai' => optional($p->tgl_selesai)->format('Y-m-d'),
            ]);

        return response()->json(['data' => $plans]);
    }

    // ── POST /api/realisasi-dinas ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::CREATE_ROLES, true), 403, 'Anda tidak berwenang mencatat realisasi dinas.');

        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'tanggal_settlement' => ['required', 'date'],
            'personil' => ['required', 'array', 'min:1'],
            'personil.*' => ['string', 'max:150'],
            'jenis_pengeluaran' => ['required', 'string', Rule::in(self::JENIS_PENGELUARAN)],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $plan = PlanAudit::findOrFail($data['plan_audit_id']);
        abort_unless($plan->status === 'done', 422, 'Realisasi Dinas hanya bisa dicatat untuk plan yang sudah selesai (done).');

        $buktiFile = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('realisasi-dinas', 'public');
            $buktiFile = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::url($path),
            ];
        }

        $realisasi = RealisasiDinas::create([
            'plan_audit_id' => $data['plan_audit_id'],
            'tanggal_settlement' => $data['tanggal_settlement'],
            'personil' => $data['personil'],
            'jenis_pengeluaran' => $data['jenis_pengeluaran'],
            'catatan' => $data['catatan'] ?? null,
            'nominal' => $data['nominal'],
            'bukti_file' => $buktiFile,
            'created_by' => $user->username,
        ]);

        return response()->json([
            'message' => 'Realisasi Dinas berhasil disimpan.',
            'data' => $realisasi->fresh('planAudit')->toAktaArray(),
        ], 201);
    }

    // ── DELETE /api/realisasi-dinas/{realisasiDinas} ─────────────────────────

    public function destroy(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->role === 'admin' || $user->username === $realisasiDinas->created_by),
            403,
            'Anda tidak berwenang menghapus data ini.'
        );

        $realisasiDinas->delete();

        return response()->json(['message' => 'Realisasi Dinas berhasil dihapus.']);
    }

    // ── GET /api/realisasi-dinas/rekap?tahun[]=&bulan[]=&jenis_pengeluaran[]=&cabang[]= ──
    // Rekap untuk grafik dashboard: total per bulan, per jenis pengeluaran, per unit usaha.

    public function rekap(Request $request): JsonResponse
    {
        $tahunInput = $request->query('tahun');
        $tahunList = collect(is_array($tahunInput) ? $tahunInput : array_filter([$tahunInput]))
            ->map(fn($t) => (int) $t)
            ->filter(fn($t) => $t >= 2000 && $t <= 2100)
            ->unique()->sort()->values();

        $bulanInput = $request->query('bulan');
        $bulanList = collect(is_array($bulanInput) ? $bulanInput : array_filter([$bulanInput]))
            ->map(fn($b) => (int) $b)
            ->filter(fn($b) => $b >= 1 && $b <= 12)
            ->unique()->sort()->values();

        $jenisInput = $request->query('jenis_pengeluaran');
        $jenisList = collect(is_array($jenisInput) ? $jenisInput : array_filter([$jenisInput]))
            ->filter()->values();

        $cabangInput = $request->query('cabang');
        $cabangList = collect(is_array($cabangInput) ? $cabangInput : array_filter([$cabangInput]))
            ->filter()->values();

        $query = RealisasiDinas::query()->with('planAudit')->whereNotNull('tanggal_settlement');

        if ($tahunList->isNotEmpty()) {
            $query->where(function ($q) use ($tahunList) {
                foreach ($tahunList as $t) {
                    $q->orWhereYear('tanggal_settlement', $t);
                }
            });
        }
        if ($bulanList->isNotEmpty()) {
            $query->where(function ($q) use ($bulanList) {
                foreach ($bulanList as $b) {
                    $q->orWhereMonth('tanggal_settlement', $b);
                }
            });
        }
        if ($jenisList->isNotEmpty()) {
            $query->whereIn('jenis_pengeluaran', $jenisList);
        }
        if ($cabangList->isNotEmpty()) {
            $query->whereHas('planAudit', fn($q) => $q->whereIn('cabang', $cabangList));
        }

        $rows = $query->get();

        $tahunOptions = RealisasiDinas::query()
            ->whereNotNull('tanggal_settlement')
            ->pluck('tanggal_settlement')
            ->map(fn($d) => (int) $d->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
        if ($tahunOptions->isEmpty()) {
            $tahunOptions = collect([now()->year]);
        }

        $cabangOptions = PlanAudit::query()
            ->whereHas('realisasiDinas')
            ->whereNotNull('cabang')
            ->orderBy('cabang')
            ->pluck('cabang')
            ->unique()
            ->values();

        $byBulan = $rows
            ->groupBy(fn($r) => optional($r->tanggal_settlement)->format('Y-m'))
            ->map(fn($group, $key) => [
                'bulan' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortBy('bulan')
            ->values();

        $byJenis = $rows
            ->groupBy(fn($r) => $r->jenis_pengeluaran ?: 'Lain-lain')
            ->map(fn($group, $key) => [
                'jenisPengeluaran' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byCabang = $rows
            ->groupBy(fn($r) => $r->planAudit?->cabang ?: '(Tanpa Unit)')
            ->map(fn($group, $key) => [
                'cabang' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byPersonil = collect();
        foreach ($rows as $row) {
            foreach (($row->personil ?? []) as $nama) {
                $byPersonil->push(['nama' => $nama, 'nominal' => (float) $row->nominal]);
            }
        }
        $byPersonilAgg = $byPersonil
            ->groupBy('nama')
            ->map(fn($group, $key) => [
                'nama' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'ok' => true,
            'tahunOptions' => $tahunOptions,
            'cabangOptions' => $cabangOptions,
            'jenisPengeluaranOptions' => self::JENIS_PENGELUARAN,
            'stats' => [
                'totalNominal' => (float) $rows->sum('nominal'),
                'jumlahEntri' => $rows->count(),
                'jumlahPlan' => $rows->pluck('plan_audit_id')->unique()->count(),
            ],
            'byBulan' => $byBulan,
            'byJenis' => $byJenis,
            'byCabang' => $byCabang,
            'byPersonil' => $byPersonilAgg,
        ]);
    }
}
