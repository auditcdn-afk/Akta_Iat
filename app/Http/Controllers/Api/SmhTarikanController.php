<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanSmhTarikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmhTarikanController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanSmhTarikan::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanSmhTarikan::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data SMH Tarikan tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }
}
