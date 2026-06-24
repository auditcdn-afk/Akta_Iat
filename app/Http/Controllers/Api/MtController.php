<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanMt;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MtController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanMt::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanMt::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['data_json' => $request->input('data', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data MT tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Ambil daftar nama tools dari SMH onhand (fallback ke db_perlengkapan)
    public function tools(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        $itemsQuery = SmhOnhandItem::query()->whereNotNull('perlengkapan_json');
        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        $allNama = [];
        foreach ($itemsQuery->get() as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama !== '') $allNama[$nama] = true;
            }
        }

        $result = array_keys($allNama);

        if (empty($result)) {
            $wilayah = $this->wilayahFromPlan($planId);
            $dbRows  = DbPerlengkapan::when($wilayah, fn($q) => $q->where('wilayah', $wilayah))->get();
            foreach ($dbRows as $row) {
                foreach ($row->itemList() as $nama) {
                    if (!in_array($nama, $result)) $result[] = $nama;
                }
            }
        }

        sort($result);
        return response()->json(['data' => $result]);
    }

    private function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;
        $plan = PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = DbUnitUsaha::where('nama', $plan->cabang)
            ->orWhere('kode', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->alamat ?? '')) : null;
    }
}
