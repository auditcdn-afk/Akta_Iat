<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PemeriksaanSmhController extends Controller
{
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    // ── GET /api/audit-detail/smh ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id') ?? $request->query('plan_id');

        $query = PemeriksaanSmh::query()->with(['items'])->latest('id');
        if ($planId) $query->where('plan_audit_id', $planId);

        return response()->json(['data' => $query->get()->map(fn($r) => $this->format($r))]);
    }

    // ── POST /api/audit-detail/smh/upload ────────────────────────────────────

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'         => 'required|file',
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
        ]);

        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // ── Parse header tanggal (row index 3) ──
        $tglOnhand = null;
        foreach ($rows as $idx => $row) {
            $first = trim((string) ($row[0] ?? ''));
            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $first)) {
                try { $tglOnhand = Carbon::parse($first)->toDateString(); } catch (\Throwable) {}
                break;
            }
            // Sometimes date is in col index 5
            $sixth = trim((string) ($row[5] ?? ''));
            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $sixth)) {
                try { $tglOnhand = Carbon::parse($sixth)->toDateString(); } catch (\Throwable) {}
                break;
            }
        }

        // ── Parse units ──
        $units = [];
        $currentKodeModelIntern = null;
        $currentKodeWarnaIntern = null;

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            $col2 = trim((string) ($row[2] ?? ''));

            // Group header: "Kode Model Intern :"
            if (str_contains(strtolower($col0), 'kode model intern')) {
                $currentKodeModelIntern = trim((string) ($row[2] ?? ''));
                $currentKodeWarnaIntern = trim((string) ($row[5] ?? ''));
                continue;
            }

            // Data row: first col is a number
            if (is_numeric($col0) && $col0 > 0 && $col1 !== '') {
                $tglSpb = null;
                $rawTgl = $row[5] ?? null;
                if (is_numeric($rawTgl)) {
                    try { $tglSpb = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawTgl))->toDateString(); } catch (\Throwable) {}
                } elseif ($rawTgl) {
                    try { $tglSpb = Carbon::parse($rawTgl)->toDateString(); } catch (\Throwable) {}
                }

                $units[] = [
                    'no_mesin'           => trim((string)($row[1] ?? '')),
                    'no_rangka'          => trim((string)($row[2] ?? '')),
                    'no_spb'             => trim((string)($row[3] ?? '')),
                    'tgl_spb'            => $tglSpb,
                    'status_spb'         => trim((string)($row[6] ?? '')),
                    'umur'               => is_numeric($row[7] ?? null) ? (int)$row[7] : null,
                    'no_do'              => trim((string)($row[8] ?? '')),
                    'kode_model'         => trim((string)($row[9] ?? '')),
                    'kode_model_intern'  => $currentKodeModelIntern,
                    'warna'              => trim((string)($row[10] ?? '')),
                    'kode_warna_intern'  => $currentKodeWarnaIntern,
                    'gudang'             => trim((string)($row[11] ?? '')),
                    'book'               => trim((string)($row[12] ?? '')),
                    'status_fisik'       => null,
                    'keterangan_fisik'   => null,
                ];
            }
        }

        // ── Upsert PemeriksaanSmh ──
        $planAuditId = (int) $request->input('plan_audit_id');
        $plan = PlanAudit::find($planAuditId);

        $pmx = PemeriksaanSmh::firstOrNew(['plan_audit_id' => $planAuditId]);
        $pmx->fill([
            'no_spt'      => $plan?->no_spt,
            'cabang'      => $plan?->cabang,
            'tgl_onhand'  => $tglOnhand,
            'total_unit'  => count($units),
            'total_ditemukan'       => 0,
            'total_tidak_ditemukan' => 0,
            'created_by'  => $this->who($request),
            'updated_by'  => $this->who($request),
        ]);
        $pmx->save();

        // Clear old items and re-insert
        $pmx->items()->delete();
        foreach (array_chunk($units, 200) as $chunk) {
            $rows2 = array_map(fn($u) => array_merge($u, [
                'pemeriksaan_smh_id' => $pmx->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]), $chunk);
            SmhOnhandItem::insert($rows2);
        }

        return response()->json([
            'message' => 'File onhand berhasil diproses. ' . count($units) . ' unit ditemukan.',
            'data'    => $this->format($pmx->load('items')),
        ], 201);
    }

    // ── PUT /api/audit-detail/smh/items/{item} ───────────────────────────────

    public function checkItem(Request $request, SmhOnhandItem $item): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $item->pemeriksaan?->plan_audit_id);

        $data = $request->validate([
            'status_fisik'       => 'required|in:ada,tidak_ada',
            'keterangan_fisik'   => 'nullable|string|max:500',
            'tgl_periksa'        => 'nullable|date',
            'keterangan_kondisi' => 'nullable|string|max:100',
            'perlengkapan_json'  => 'nullable|array',
        ]);

        $item->update(array_merge($data, ['checked_at' => now()]));

        $pmx = $item->pemeriksaan;
        $pmx->total_ditemukan       = $pmx->items()->where('status_fisik', 'ada')->count();
        $pmx->total_tidak_ditemukan = $pmx->items()->where('status_fisik', 'tidak_ada')->count();
        $pmx->updated_by = $this->who($request);
        $pmx->save();

        return response()->json(['message' => 'Status fisik diperbarui.', 'data' => $this->formatItem($item->fresh())]);
    }

    // ── GET /api/audit-detail/smh/perlengkapan?kode=JBK1E ────────────────────
    // Ambil daftar perlengkapan berdasarkan 5 huruf prefix no_mesin

    public function perlengkapan(Request $request): JsonResponse
    {
        $kode    = strtoupper(trim((string) $request->query('kode', '')));
        if (!$kode) return response()->json(['data' => null, 'items' => []]);

        $planId  = $request->query('plan_audit_id');
        $wilayah = $this->wilayahFromPlan($planId);
        $row     = $this->findPerlengkapan($kode, $wilayah);

        return response()->json([
            'data'  => $row,
            'items' => $row ? $row->itemList() : [],
            'nama'  => $row?->nama,
            'tipe'  => $row?->satuan,
        ]);
    }

    // ── GET /api/audit-detail/smh/scan ───────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => null, 'message' => 'Minimal 2 karakter.']);
        }

        $planId = $request->query('plan_audit_id');

        $query = SmhOnhandItem::query()
            ->where(fn($q2) => $q2->where('no_mesin', 'like', "%{$q}%")
                ->orWhere('no_rangka', 'like', "%{$q}%"));

        if ($planId) {
            $query->whereHas('pemeriksaan', fn($q2) => $q2->where('plan_audit_id', $planId));
        }

        $item = $query->first();

        // Ambil perlengkapan berdasarkan prefix no_mesin + wilayah unit usaha plan
        $perlengkapan = [];
        if ($item) {
            $prefix  = strtoupper(substr(str_replace(' ', '', $item->no_mesin ?? ''), 0, 5));
            $wilayah = $this->wilayahFromPlan($planId);
            $plRow   = $this->findPerlengkapan($prefix, $wilayah);
            $perlengkapan = $plRow ? $plRow->itemList() : [];
        }

        return response()->json([
            'data'         => $item ? $this->formatItem($item) : null,
            'perlengkapan' => $perlengkapan,
            'message'      => $item ? 'Unit ditemukan.' : 'Unit tidak ditemukan dalam daftar onhand.',
        ]);
    }

    // ── GET /api/audit-detail/smh/{pmx}/sync-perlengkapan ────────────────────

    public function syncPerlengkapan(PemeriksaanSmh $pemeriksaanSmh): JsonResponse
    {
        $items   = $pemeriksaanSmh->items()->get();
        $wilayah = $this->wilayahFromPlan((string) $pemeriksaanSmh->plan_audit_id);

        $grouped = [];
        foreach ($items as $it) {
            $prefix = strtoupper(substr(str_replace(' ', '', $it->no_mesin ?? ''), 0, 5));
            if (!isset($grouped[$prefix])) {
                $plRow = $this->findPerlengkapan($prefix, $wilayah);
                $grouped[$prefix] = [
                    'kode'         => $prefix,
                    'nama'         => $plRow?->nama,
                    'items_db'     => $plRow ? $plRow->itemList() : [],
                    'matched'      => $plRow !== null,
                    'total_unit'   => 0,
                    'total_lengkap'=> 0,
                ];
            }
            $grouped[$prefix]['total_unit']++;
            if ($it->status_fisik === 'ada' && $it->perlengkapan_json) {
                $allAda = collect($it->perlengkapan_json)->every(fn($p) => $p['ada'] ?? false);
                if ($allAda) $grouped[$prefix]['total_lengkap']++;
            }
        }

        return response()->json(['data' => array_values($grouped)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function format(PemeriksaanSmh $pmx): array
    {
        return [
            'id'                   => $pmx->id,
            'planAuditId'          => $pmx->plan_audit_id,
            'noSpt'                => $pmx->no_spt,
            'cabang'               => $pmx->cabang,
            'tglOnhand'            => $pmx->tgl_onhand?->toDateString(),
            'totalUnit'            => $pmx->total_unit,
            'totalDitemukan'       => $pmx->total_ditemukan,
            'totalTidakDitemukan'  => $pmx->total_tidak_ditemukan,
            'totalBelumDiperiksa'  => $pmx->total_unit - $pmx->total_ditemukan - $pmx->total_tidak_ditemukan,
            'keterangan'           => $pmx->keterangan,
            'items'                => $pmx->relationLoaded('items')
                ? $pmx->items->map(fn($i) => $this->formatItem($i))->values()
                : null,
        ];
    }

    private function formatItem(SmhOnhandItem $i): array
    {
        return [
            'id'              => $i->id,
            'noMesin'         => $i->no_mesin,
            'noRangka'        => $i->no_rangka,
            'noSpb'           => $i->no_spb,
            'tglSpb'          => $i->tgl_spb?->toDateString(),
            'statusSpb'       => $i->status_spb,
            'umur'            => $i->umur,
            'noDo'            => $i->no_do,
            'kodeModel'       => $i->kode_model,
            'kodeModelIntern' => $i->kode_model_intern,
            'warna'           => $i->warna,
            'kodeWarnaIntern' => $i->kode_warna_intern,
            'gudang'          => $i->gudang,
            'book'            => $i->book,
            'statusFisik'        => $i->status_fisik,
            'keteranganFisik'    => $i->keterangan_fisik,
            'checkedAt'          => $i->checked_at?->toDateTimeString(),
            'tglPeriksa'         => $i->tgl_periksa?->toDateString(),
            'keteranganKondisi'  => $i->keterangan_kondisi,
            'perlengkapanJson'   => $i->perlengkapan_json ?? [],
        ];
    }

    /** Resolve wilayah string from plan's unit usaha. */
    private function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;
        $plan = \App\Models\PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = \App\Models\DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->wilayah ?? '')) : null;
    }

    /** Find DbPerlengkapan by kode + wilayah, fallback to any wilayah for same kode. */
    private function findPerlengkapan(string $kode, ?string $wilayah = null): ?DbPerlengkapan
    {
        try {
            $q = DbPerlengkapan::where('kode', $kode);
            if ($wilayah) {
                // Try exact wilayah first, then fallback to null wilayah
                $row = (clone $q)->where('wilayah', $wilayah)->first()
                    ?? (clone $q)->whereNull('wilayah')->first()
                    ?? $q->first();
                return $row;
            }
            return $q->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    // ── POST /api/audit-detail/smh/manual ────────────────────────────────────

    public function storeManual(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));

        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'no_mesin'      => ['required', 'string', 'max:80'],
            'no_rangka'     => ['required', 'string', 'max:80'],
            'gudang'        => ['nullable', 'string', 'max:80'],
        ]);

        $planId = (int) $data['plan_audit_id'];
        $plan   = PlanAudit::findOrFail($planId);

        // Ambil atau buat record PemeriksaanSmh untuk plan ini
        $smh = PemeriksaanSmh::firstOrCreate(
            ['plan_audit_id' => $planId],
            [
                'no_spt'     => $plan->no_spt,
                'cabang'     => $plan->cabang,
                'created_by' => $this->who($request),
            ]
        );

        $item = SmhOnhandItem::create([
            'pemeriksaan_smh_id' => $smh->id,
            'no_mesin'           => strtoupper(trim($data['no_mesin'])),
            'no_rangka'          => strtoupper(trim($data['no_rangka'])),
            'gudang'             => $data['gudang'] ?? null,
            'status_fisik'       => 'ada',
            'keterangan_fisik'   => 'Input Manual',
        ]);

        // Update total_unit di SMH header
        $smh->total_unit      = $smh->items()->count();
        $smh->total_ditemukan = $smh->items()->whereNotNull('status_fisik')->where('status_fisik', 'ada')->count();
        $smh->updated_by      = $this->who($request);
        $smh->save();

        // Auto-sync perlengkapan untuk item ini
        $prefix  = strtoupper(substr(str_replace(' ', '', $item->no_mesin), 0, 5));
        $wilayah = $this->wilayahFromPlan((string) $planId);
        $plRow   = $this->findPerlengkapan($prefix, $wilayah);
        $perlengkapan = $plRow ? $plRow->itemList() : [];

        return response()->json([
            'message'      => 'Unit berhasil ditambahkan secara manual.',
            'item'         => $this->formatItem($item),
            'perlengkapan' => $perlengkapan,
            'smh'          => $this->format($smh->load('items')),
        ], 201);
    }

    private function ensureCanWrite(Request $request, int $planAuditId = 0): void
    {
        if ($planAuditId && PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists()) {
            return;
        }

        abort_unless(in_array($this->role($request), $this->writeRoles, true), 403, 'Role tidak diizinkan.');
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function who(Request $request): ?string
    {
        $u = $request->user();
        return $u?->username ?? $u?->email ?? null;
    }
}
