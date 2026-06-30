<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditGrading;
use App\Models\DbGrading;
use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradingController extends Controller
{
    // Konversi nilai dengan format koma Indonesia ke float
    private static function toFloat($val): float
    {
        if ($val === null || $val === '') return 0.0;
        // Ganti koma desimal → titik, hapus karakter non-numerik kecuali titik & minus
        $clean = str_replace(',', '.', (string)$val);
        $clean = preg_replace('/[^0-9.\-]/', '', $clean);
        return (float)$clean;
    }

    // Map jenis audit → jenis grading
    private const JENIS_MAP = [
        'H1' => 'Cabang',
        'H2' => 'Bengkel',
        'WHS PART' => 'WHS PART',
        'WHS UNIT' => 'WHS UNIT',
    ];

    public function show(Request $request): JsonResponse
    {
        try {
            $planId = $request->query('plan_audit_id');
            $rec    = AuditGrading::where('plan_audit_id', $planId)->first();
            return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
        } catch (\Exception $e) {
            // Tabel belum dibuat — kembalikan null agar UI tidak crash
            return response()->json(['data' => null]);
        }
    }

    public function save(Request $request): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), '42S02')) {
                return response()->json([
                    'message' => 'Tabel audit_gradings belum ada. Jalankan: php artisan migrate',
                    'migrate' => true,
                ], 500);
            }
            return response()->json(['message' => $e->getMessage()], 500);
        }
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

        // Kelompokkan per nama_pemeriksaan → array hasil yang mungkin (deduplikasi by label)
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
                    '_hasilLabels'    => [],   // tracking deduplikasi, dihapus sebelum return
                ];
            }
            if ($r->hasil_pemeriksaan) {
                $labelIdx = array_search($r->hasil_pemeriksaan, $grouped[$key]['_hasilLabels']);
                if ($labelIdx === false) {
                    // Belum ada — tambahkan
                    $grouped[$key]['_hasilLabels'][] = $r->hasil_pemeriksaan;
                    $grouped[$key]['hasilOptions'][] = [
                        'label' => $r->hasil_pemeriksaan,
                        'nilai' => self::toFloat($r->nilai),
                        'bknf'  => $r->bknf,
                        'pknf'  => self::toFloat($r->pknf),
                        'bkf'   => $r->bkf,
                        'pkf'   => self::toFloat($r->pkf),
                        'bnknf' => $r->bnknf,
                        'pnknf' => self::toFloat($r->pnknf),
                        'bnkf'  => $r->bnkf,
                        'pnkf'  => self::toFloat($r->pnkf),
                    ];
                } else {
                    // Sudah ada — update hanya kolom yang masih 0 dengan nilai non-zero dari baris ini
                    $existing = &$grouped[$key]['hasilOptions'][$labelIdx];
                    foreach (['nilai','pknf','pkf','pnknf','pnkf'] as $col) {
                        if (($existing[$col] ?? 0) == 0 && self::toFloat($r->$col) != 0) {
                            $existing[$col] = self::toFloat($r->$col);
                        }
                    }
                }
            }
        }

        // Hapus field tracking sebelum di-return
        foreach ($grouped as &$g) unset($g['_hasilLabels']);

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

        // Ambil wilayah dari db_unit_usaha berdasarkan cabang
        $unitUsaha = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        $wilayah   = $unitUsaha?->wilayah ?? $plan->cabang_area ?? '';

        return response()->json(['data' => [
            'cabang'       => $plan->cabang,
            'area'         => $wilayah,
            'jenisAudit'   => $plan->jenis_audit,
            'jenisGrading' => $jenisGrading,
        ]]);
    }
}
