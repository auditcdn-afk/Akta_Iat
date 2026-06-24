<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbMt;
use App\Models\PemeriksaanMt;
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

    // Ambil daftar tools dari db_mt, dikelompokkan per jenis
    public function tools(Request $request): JsonResponse
    {
        $jenis = $request->query('jenis'); // 'baru' | 'lama' | 'fi'

        $jenisMap = [
            'baru' => 'MT Baru',
            'lama' => 'MT Lama',
            'fi'   => 'MT FI',
        ];

        $query = DbMt::orderBy('nomor');

        if ($jenis && isset($jenisMap[$jenis])) {
            $query->where('jenis', $jenisMap[$jenis]);
        }

        $rows = $query->get()->map(fn($r) => [
            'nama'          => $r->nama_peralatan ?: $r->nama_singkat,
            'namaSingkat'   => $r->nama_singkat,
            'kode'          => $r->kode_peralatan,
            'jenis'         => $r->jenis,
        ]);

        return response()->json(['data' => $rows]);
    }
}
