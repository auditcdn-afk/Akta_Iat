<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditGrading;
use App\Models\DbGrading;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradingController extends Controller
{
    // Map jenis audit → jenis grading
    private const JENIS_MAP = [
        'H1' => 'Cabang',
        'H2' => 'Bengkel',
        'WHS PART' => 'WHS PART',
        'WHS UNIT' => 'WHS UNIT',
    ];

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = AuditGrading::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = AuditGrading::updateOrCreate(
            ['plan_audit_id' => $planId],
            [
                'id_grading'       => $request->input('idGrading'),
                'jenis'            => $request->input('jenis'),
                'area'             => $request->input('area'),
                'bbnkb'            => $request->input('bbnkb', 'N'),
                'fraud'            => $request->input('fraud', 'N'),
                'jenis_fraud'      => $request->input('jenisFraud', []),
                'keterangan_fraud' => $request->input('keteranganFraud'),
                'details'          => $request->input('details', []),
                'total_nilai'      => $request->input('totalNilai'),
                'updated_by'       => $who,
            ]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Grading tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Master: daftar pemeriksaan + pilihan hasil berdasarkan jenis & wilayah
    public function master(Request $request): JsonResponse
    {
        $jenis   = $request->query('jenis');   // Cabang, Bengkel, WHS PART, WHS UNIT
        $wilayah = $request->query('wilayah'); // RRI, dll

        $query = DbGrading::query();
        if ($jenis)   $query->where('jenis', $jenis);
        if ($wilayah) $query->where('wilayah', $wilayah);

        $rows = $query->orderBy('nama_pemeriksaan')->get();

        // Kelompokkan per nama_pemeriksaan → array hasil yang mungkin
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->nama_pemeriksaan;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'namaPemeriksaan' => $key,
                    'idGrading'       => $r->id_grading,
                    'jenis'           => $r->jenis,
                    'wilayah'         => $r->wilayah,
                    'hasilOptions'    => [],
                ];
            }
            if ($r->hasil_pemeriksaan) {
                $grouped[$key]['hasilOptions'][] = [
                    'label' => $r->hasil_pemeriksaan,
                    'nilai' => (float)$r->nilai,
                    'bknf'  => $r->bknf,
                    'pknf'  => (float)$r->pknf,
                    'bkf'   => $r->bkf,
                    'pkf'   => (float)$r->pkf,
                ];
            }
        }

        return response()->json([
            'data'  => array_values($grouped),
            'total' => count($grouped),
        ]);
    }

    // Daftar jenis unik dari db_grading
    public function jenisOptions(): JsonResponse
    {
        $jenis = DbGrading::select('jenis')->distinct()->whereNotNull('jenis')
            ->orderBy('jenis')->pluck('jenis');
        return response()->json(['data' => $jenis]);
    }

    // Daftar wilayah unik dari db_grading
    public function wilayahOptions(): JsonResponse
    {
        $wilayah = DbGrading::select('wilayah')->distinct()->whereNotNull('wilayah')
            ->orderBy('wilayah')->pluck('wilayah');
        return response()->json(['data' => $wilayah]);
    }

    // Info plan audit untuk auto-fill jenis & area
    public function planInfo(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $plan   = PlanAudit::find($planId);
        if (!$plan) return response()->json(['data' => null]);

        // Map jenis audit ke jenis grading
        $jenisAudit   = strtoupper(trim($plan->jenis_audit ?? ''));
        $jenisGrading = self::JENIS_MAP[$jenisAudit] ?? null;

        return response()->json(['data' => [
            'cabang'       => $plan->cabang,
            'area'         => $plan->cabang_area,
            'jenisAudit'   => $plan->jenis_audit,
            'jenisGrading' => $jenisGrading,
        ]]);
    }
}
