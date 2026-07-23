<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\RealisasiDinas;
use App\Models\RealisasiDinasItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RealisasiDinasController extends Controller
{
    private const EDIT_ROLES = ['admin', 'manajer', 'auditor', 'koordinator'];

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

    private function assertEditable(Request $request, RealisasiDinas $realisasiDinas): void
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::EDIT_ROLES, true), 403, 'Anda tidak berwenang mengubah realisasi dinas.');
        abort_if($realisasiDinas->isLocked(), 422, 'Realisasi Dinas ini sudah dikunci (selesai). Minta admin membuka kunci untuk mengubahnya.');
    }

    private function personilFromPlanTeam(PlanAudit $plan): array
    {
        return collect(array_merge([$plan->kepala_tim], $plan->tim ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ── GET /api/realisasi-dinas?jenis_pengeluaran=&tahun=&bulan=&cabang= ──
    // Listing semua header untuk menu utama Realisasi Dinas.

    public function index(Request $request): JsonResponse
    {
        $rows = RealisasiDinas::query()
            ->with(['planAudit', 'items'])
            ->when($request->filled('tahun'), fn($q) => $q->whereYear('created_at', $request->query('tahun')))
            ->when($request->filled('bulan'), fn($q) => $q->whereMonth('created_at', $request->query('bulan')))
            ->when($request->filled('cabang'), fn($q) => $q->whereHas('planAudit', fn($p) => $p->where('cabang', $request->query('cabang'))))
            ->when($request->filled('jenis_pengeluaran'), function ($q) use ($request) {
                $jenis = $request->query('jenis_pengeluaran');
                $q->whereHas('items', fn($i) => $i->where('jenis_pengeluaran', $jenis));
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map->toAktaArray(),
            'jenisPengeluaranOptions' => self::JENIS_PENGELUARAN,
        ]);
    }

    // GET /api/realisasi-dinas/plan-options — plan "done" yang belum punya realisasi dinas.

    public function planOptions(Request $request): JsonResponse
    {
        $plans = PlanAudit::query()
            ->where('status', 'done')
            ->doesntHave('realisasiDinas')
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

    // ── GET /api/realisasi-dinas/plan/{plan} ─────────────────────────────────
    // Ambil (atau buat baru) header realisasi dinas untuk satu plan — dikunci
    // per plan ini, personil otomatis diisi dari tim/kepala tim plan tersebut.

    public function showForPlan(Request $request, PlanAudit $plan): JsonResponse
    {
        abort_unless($plan->status === 'done', 422, 'Realisasi Dinas hanya bisa dicatat untuk plan yang sudah selesai (done).');

        $realisasi = RealisasiDinas::query()->where('plan_audit_id', $plan->id)->first();

        if (!$realisasi) {
            $user = $request->user();
            abort_unless($user && in_array($user->role, self::EDIT_ROLES, true), 403, 'Anda tidak berwenang mencatat realisasi dinas.');

            $realisasi = RealisasiDinas::create([
                'plan_audit_id' => $plan->id,
                'personil' => $this->personilFromPlanTeam($plan),
                'status' => 'draft',
                'created_by' => $user->username,
            ]);
        }

        return response()->json([
            'data' => $realisasi->load(['planAudit', 'items'])->toAktaArray(),
            'jenisPengeluaranOptions' => self::JENIS_PENGELUARAN,
        ]);
    }

    // ── PUT /api/realisasi-dinas/{realisasiDinas}/personil ───────────────────

    public function updatePersonil(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $this->assertEditable($request, $realisasiDinas);

        $data = $request->validate([
            'personil' => ['required', 'array', 'min:1'],
            'personil.*' => ['string', 'max:150'],
        ]);

        $realisasiDinas->update(['personil' => $data['personil']]);

        return response()->json([
            'message' => 'Personil berhasil diperbarui.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ]);
    }

    // ── POST /api/realisasi-dinas/{realisasiDinas}/bukti ─────────────────────

    public function uploadBukti(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $this->assertEditable($request, $realisasiDinas);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($realisasiDinas->bukti_file['path'] ?? null) {
            Storage::disk('public')->delete($realisasiDinas->bukti_file['path']);
        }

        $file = $request->file('file');
        $path = $file->store('realisasi-dinas', 'public');
        $realisasiDinas->update([
            'bukti_file' => [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::url($path),
            ],
        ]);

        return response()->json([
            'message' => 'Bukti berhasil diunggah.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ]);
    }

    // ── POST /api/realisasi-dinas/{realisasiDinas}/items ─────────────────────

    public function addItem(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $this->assertEditable($request, $realisasiDinas);

        $data = $request->validate([
            'jenis_pengeluaran' => ['required', 'string', Rule::in(self::JENIS_PENGELUARAN)],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'nominal' => ['required', 'numeric', 'min:0'],
        ]);

        $realisasiDinas->items()->create($data);

        return response()->json([
            'message' => 'Item pengeluaran berhasil ditambahkan.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ], 201);
    }

    // ── DELETE /api/realisasi-dinas/items/{realisasiDinasItem} ───────────────

    public function deleteItem(Request $request, RealisasiDinasItem $realisasiDinasItem): JsonResponse
    {
        $realisasiDinas = $realisasiDinasItem->realisasiDinas;
        $this->assertEditable($request, $realisasiDinas);

        $realisasiDinasItem->delete();

        return response()->json([
            'message' => 'Item pengeluaran berhasil dihapus.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ]);
    }

    // ── POST /api/realisasi-dinas/{realisasiDinas}/selesai ───────────────────

    public function selesai(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::EDIT_ROLES, true), 403, 'Anda tidak berwenang mengunci realisasi dinas.');
        abort_if($realisasiDinas->isLocked(), 422, 'Realisasi Dinas ini sudah dikunci sebelumnya.');
        abort_if($realisasiDinas->items()->count() === 0, 422, 'Tambahkan minimal satu item pengeluaran sebelum menyelesaikan.');

        $realisasiDinas->update([
            'status' => 'selesai',
            'locked_at' => now(),
            'locked_by' => $user->username,
        ]);

        return response()->json([
            'message' => 'Realisasi Dinas berhasil dikunci.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ]);
    }

    // ── POST /api/realisasi-dinas/{realisasiDinas}/buka-kunci ────────────────
    // Hanya admin yang boleh membuka kunci realisasi dinas yang sudah selesai.

    public function bukaKunci(Request $request, RealisasiDinas $realisasiDinas): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403, 'Hanya admin yang boleh membuka kunci realisasi dinas.');
        abort_unless($realisasiDinas->isLocked(), 422, 'Realisasi Dinas ini belum dikunci.');

        $realisasiDinas->update([
            'status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return response()->json([
            'message' => 'Realisasi Dinas berhasil dibuka kembali.',
            'data' => $realisasiDinas->fresh(['planAudit', 'items'])->toAktaArray(),
        ]);
    }

    // ── GET /api/realisasi-dinas/rekap?tahun[]=&bulan[]=&jenis_pengeluaran[]=&cabang[]= ──
    // Rekap untuk grafik dashboard: total per bulan, per jenis pengeluaran, per unit usaha, per personil.

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

        $query = RealisasiDinas::query()->with(['planAudit', 'items']);

        if ($tahunList->isNotEmpty()) {
            $query->where(function ($q) use ($tahunList) {
                foreach ($tahunList as $t) {
                    $q->orWhereYear('created_at', $t);
                }
            });
        }
        if ($bulanList->isNotEmpty()) {
            $query->where(function ($q) use ($bulanList) {
                foreach ($bulanList as $b) {
                    $q->orWhereMonth('created_at', $b);
                }
            });
        }
        if ($cabangList->isNotEmpty()) {
            $query->whereHas('planAudit', fn($q) => $q->whereIn('cabang', $cabangList));
        }
        if ($jenisList->isNotEmpty()) {
            $query->whereHas('items', fn($q) => $q->whereIn('jenis_pengeluaran', $jenisList));
        }

        $headers = $query->get();

        // Ratakan seluruh item dari header yang lolos filter, sekaligus bawa
        // konteks header (bulan, cabang, personil) untuk agregasi per item.
        $rows = collect();
        foreach ($headers as $header) {
            foreach ($header->items as $item) {
                if ($jenisList->isNotEmpty() && !$jenisList->contains($item->jenis_pengeluaran)) {
                    continue;
                }
                $rows->push([
                    'bulan' => optional($header->created_at)->format('Y-m'),
                    'tahun' => optional($header->created_at)->format('Y'),
                    'cabang' => $header->planAudit?->cabang ?: '(Tanpa Unit)',
                    'jenisPengeluaran' => $item->jenis_pengeluaran,
                    'nominal' => (float) $item->nominal,
                    'personil' => $header->personil ?? [],
                ]);
            }
        }

        $tahunOptions = RealisasiDinas::query()
            ->pluck('created_at')
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
            ->groupBy('bulan')
            ->map(fn($group, $key) => [
                'bulan' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortBy('bulan')
            ->values();

        $byJenis = $rows
            ->groupBy(fn($r) => $r['jenisPengeluaran'] ?: 'Lain-lain')
            ->map(fn($group, $key) => [
                'jenisPengeluaran' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byCabang = $rows
            ->groupBy('cabang')
            ->map(fn($group, $key) => [
                'cabang' => $key,
                'total' => (float) $group->sum('nominal'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byPersonil = collect();
        foreach ($rows as $row) {
            foreach (($row['personil'] ?? []) as $nama) {
                $byPersonil->push(['nama' => $nama, 'nominal' => $row['nominal']]);
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
                'jumlahPlan' => $headers->pluck('plan_audit_id')->unique()->count(),
            ],
            'byBulan' => $byBulan,
            'byJenis' => $byJenis,
            'byCabang' => $byCabang,
            'byPersonil' => $byPersonilAgg,
        ]);
    }
}
