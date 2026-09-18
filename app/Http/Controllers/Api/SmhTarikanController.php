<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MengunciDataPemeriksaan;
use App\Http\Controllers\Concerns\MenolakTimpaanBasi;
use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\PemeriksaanSmhTarikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmhTarikanController extends Controller
{
    use RequiresAuditorAuditee;
    use MengunciDataPemeriksaan;
    use MenolakTimpaanBasi;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanSmhTarikan::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'smh-tarikan');
        $who    = $request->user()?->username ?? $request->user()?->email;

        return $this->denganKunciPemeriksaan(PemeriksaanSmhTarikan::class, $planId,
            function (?PemeriksaanSmhTarikan $rec) use ($request, $planId, $who) {
                $this->tolakKalauBasi($rec, $request, 'SMH Tarikan');

                $rec = PemeriksaanSmhTarikan::updateOrCreate(
                    ['plan_audit_id' => $planId],
                    ['items_json' => $request->input('items', []), 'updated_by' => $who]
                );
                if (!$rec->created_by) $rec->update(['created_by' => $who]);

                return response()->json(['message' => 'Data SMH Tarikan tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
            });
    }
}
