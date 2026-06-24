<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanKwitansi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KwitansiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanKwitansi::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $payload = [
            'tgl_audit'     => $request->input('tglAudit') ?: null,
            'kwitansi_json' => $request->input('kwitansi', []),
            'updated_by'    => $who,
        ];

        $rec = PemeriksaanKwitansi::updateOrCreate(
            ['plan_audit_id' => $planId],
            $payload
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }
}
