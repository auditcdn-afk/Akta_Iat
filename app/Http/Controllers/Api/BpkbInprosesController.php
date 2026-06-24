<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanBpkbInproses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BpkbInprosesController extends Controller
{
    // ── GET /api/audit-detail/bpkb-inproses?plan_audit_id= ───────────────────

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanBpkbInproses::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    // ── POST /api/audit-detail/bpkb-inproses ─────────────────────────────────

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $payload = [
            'tgl_awal'                   => $request->input('tglAwal') ?: null,
            'saldo_awal_fisik'           => (int) $request->input('saldoAwalFisik', 0),
            'penerimaan_fisik_json'      => $request->input('penerimaanFisik', []),
            'pengeluaran_bpkb_json'      => $request->input('pengeluaranBpkb', []),
            'fisik_bpkb_hitung'          => $request->input('fisikBpkbHitung') !== null ? (int)$request->input('fisikBpkbHitung') : null,
            'keterangan_selisih'         => $request->input('keteranganSelisih'),
            'filter_inproses'            => $request->input('filterInproses'),
            'saldo_awal_inproses'        => (int) $request->input('saldoAwalInproses', 0),
            'pendaftaran_bpkb_json'      => $request->input('pendaftaranBpkb', []),
            'penyelesaian_inproses_json' => $request->input('penyelesaianInproses', []),
            'fisik_inproses_hitung'      => $request->input('fisikInprosesHitung') !== null ? (int)$request->input('fisikInprosesHitung') : null,
            'ket_selisih_inproses_json'  => $request->input('ketSelisihInproses', []),
            'rincian_inproses_json'      => $request->input('rincianInproses', []),
            'onhand_bpkb'                => (int) $request->input('onhandBpkb', 0),
            'keterangan_selisih_onhand'  => $request->input('keteranganSelisihOnhand'),
            'updated_by'                 => $who,
        ];

        $rec = PemeriksaanBpkbInproses::updateOrCreate(
            ['plan_audit_id' => $planId],
            $payload
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }
}
