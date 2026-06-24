<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PemeriksaanPerlengkapanController extends Controller
{
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    // ── GET /api/audit-detail/perlengkapan ───────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $q = PemeriksaanPerlengkapan::query()->latest('id');
        if ($planId) $q->where('plan_audit_id', $planId);

        return response()->json(['data' => $q->get()->map->toAktaArray()]);
    }

    // ── GET /api/audit-detail/perlengkapan/jenis ─────────────────────────────
    // Ambil daftar jenis perlengkapan dari onhand yang sudah diperiksa (perlengkapan_json)
    // disinkronkan dengan db_perlengkapan, dikelompokkan per item perlengkapan.

    public function jenis(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Ambil semua item onhand dari plan ini
        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        $items = $itemsQuery->get();

        // Kumpulkan semua nama perlengkapan unik dari semua unit yang sudah diperiksa
        $allNama = [];
        foreach ($items as $item) {
            $plJson = $item->perlengkapan_json ?? [];
            foreach ($plJson as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama !== '') {
                    $allNama[$nama] = ($allNama[$nama] ?? 0) + 1;
                }
            }
        }

        // Urutkan berdasarkan frekuensi kemunculan
        arsort($allNama);

        $result = array_keys($allNama);

        // Jika belum ada data onhand, fallback ke db_perlengkapan
        if (empty($result) && $planId) {
            $wilayah = $this->wilayahFromPlan($planId);
            $dbRows  = DbPerlengkapan::when($wilayah, fn($q) => $q->where('wilayah', $wilayah))
                ->get();
            foreach ($dbRows as $row) {
                foreach ($row->itemList() as $nama) {
                    if (!in_array($nama, $result)) $result[] = $nama;
                }
            }
        }

        return response()->json(['data' => $result]);
    }

    // ── GET /api/audit-detail/perlengkapan/smh-summary ───────────────────────
    // Hitung jumlah per jenis perlengkapan dari onhand (untuk qty referensi)

    public function smhSummary(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json')
            ->where('status_fisik', 'ada');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        $items = $itemsQuery->get();

        // Total unit onhand untuk plan ini (semua item, tidak hanya yang punya perlengkapan_json)
        $totalOnhandQuery = SmhOnhandItem::query();
        if ($planId) {
            $totalOnhandQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }
        $totalOnhand = $totalOnhandQuery->count();

        // Hitung per item: ada vs total di setiap unit
        $summary = [];
        foreach ($items as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama === '') continue;
                if (!isset($summary[$nama])) {
                    $summary[$nama] = ['nama' => $nama, 'ada' => 0, 'total' => 0, 'totalOnhand' => $totalOnhand];
                }
                $summary[$nama]['total']++;
                if ($pl['ada'] ?? false) $summary[$nama]['ada']++;
            }
        }

        return response()->json(['data' => array_values($summary)]);
    }

    // ── POST /api/audit-detail/perlengkapan ──────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request);

        $data = $request->validate([
            'plan_audit_id'     => 'required|integer|exists:plan_audits,id',
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'required|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        $saldo = (float) ($data['saldo'] ?? 0);
        $fisik = (int)   ($data['fisik'] ?? 0);

        // selisih = fisik - saldo (kelebihan/kekurangan fisik vs saldo buku)
        $data['selisih']    = $fisik - $saldo;
        $data['created_by'] = $this->who($request);
        $data['updated_by'] = $this->who($request);

        $rec = PemeriksaanPerlengkapan::create($data);

        return response()->json(['message' => 'Data berhasil disimpan.', 'data' => $rec->toAktaArray()], 201);
    }

    // ── PUT /api/audit-detail/perlengkapan/{rec} ─────────────────────────────

    public function update(Request $request, PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $this->ensureCanWrite($request);

        $data = $request->validate([
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'nullable|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        $saldo = (float) ($data['saldo'] ?? $pemeriksaanPerlengkapan->saldo);
        $fisik = (int)   ($data['fisik'] ?? $pemeriksaanPerlengkapan->fisik);
        $data['selisih']    = $fisik - $saldo;
        $data['updated_by'] = $this->who($request);

        $pemeriksaanPerlengkapan->update($data);

        return response()->json(['message' => 'Data berhasil diperbarui.', 'data' => $pemeriksaanPerlengkapan->fresh()->toAktaArray()]);
    }

    // ── DELETE /api/audit-detail/perlengkapan/{rec} ──────────────────────────

    public function destroy(PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $pemeriksaanPerlengkapan->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;
        $plan = PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = DbUnitUsaha::where('nama', $plan->cabang)
            ->orWhere('kode', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->alamat ?? '')) : null;
    }

    private function ensureCanWrite(Request $request): void
    {
        abort_unless(in_array(strtolower($request->user()?->role ?? ''), $this->writeRoles, true), 403, 'Role tidak diizinkan.');
    }

    private function who(Request $request): ?string
    {
        $u = $request->user();
        return $u?->username ?? $u?->email ?? null;
    }
}
