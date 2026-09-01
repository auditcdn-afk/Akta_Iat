<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbMt;
use App\Models\PemeriksaanMt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MtController extends Controller
{
    use RequiresAuditorAuditee;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanMt::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'mt');
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
            'harga'         => $r->harga !== null ? (float) $r->harga : null,
            'jenis'         => $r->jenis,
        ]);

        // Dedupe by nama (trim+lowercase) — db_mt kadang punya baris ganda untuk
        // nama tool yang sama (mis. hasil import Excel dengan baris terduplikasi).
        // Tanpa ini, tiap tool yang ganda ikut ke-auto-isi 2x ke kategori Bagus
        // saat mekanik baru pertama kali dibuka, dan salah satu duplikatnya tetap
        // "available" untuk dipilih di kategori lain walau kelihatan sudah ada.
        $rows = $rows->unique(fn($r) => strtolower(trim($r['nama'])))->values();

        return response()->json(['data' => $rows]);
    }
}
