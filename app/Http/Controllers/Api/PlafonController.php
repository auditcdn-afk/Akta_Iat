<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbHargaSmh;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlafonController extends Controller
{
    // ── GET /api/audit-detail/plafon/analisa ─────────────────────────────────
    // Analisa plafon: total harga SMH dari onhand vs plafon cover per unit usaha
    //
    // Relasi data:
    //   smh_onhand_items.gudang     → db_unit_usaha.kode  → nama, alamat (wilayah)
    //   smh_onhand_items.kode_model → db_harga_smh.kode_model → harga
    //   db_unit_usaha.kode          → db_plafon.kode      → nilai (plafon cover)

    public function analisa(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Semua onhand items untuk plan ini
        $items = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
            ->get();

        // Map harga SMH per kode_model (uppercase key)
        $hargaMap = DbHargaSmh::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));

        // Map unit usaha per kode (uppercase key)
        $unitMap = DbUnitUsaha::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode ?? '')));

        // Map plafon per kode unit usaha (uppercase key)
        $plafonMap = DbPlafon::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode ?? '')));

        // Kelompokkan per gudang (= kode unit usaha)
        $grouped = [];
        foreach ($items as $item) {
            $gudang    = strtoupper(trim($item->gudang ?? '-'));
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;
            $unitRow   = $unitMap[$gudang] ?? null;

            if (!isset($grouped[$gudang])) {
                $plafonRow = $plafonMap[$gudang] ?? null;
                $grouped[$gudang] = [
                    'gudang'      => $gudang,
                    'namaUnit'    => $unitRow?->nama ?? $gudang,
                    'wilayah'     => $unitRow?->alamat ?? '—',
                    'plafonNilai' => $plafonRow ? (float) $plafonRow->nilai : null,
                    'plafonNama'  => $plafonRow?->nama ?? null,
                    'totalUnit'   => 0,
                    'ditemukan'   => 0,
                    'tidakDitemukan' => 0,
                    'totalNilai'  => 0.0,
                    'detail'      => [],
                ];
            }

            $grouped[$gudang]['totalUnit']++;
            if ($harga !== null) {
                $grouped[$gudang]['ditemukan']++;
                $grouped[$gudang]['totalNilai'] += $harga;
            } else {
                $grouped[$gudang]['tidakDitemukan']++;
            }

            $grouped[$gudang]['detail'][] = [
                'noMesin'    => $item->no_mesin,
                'noRangka'   => $item->no_rangka,
                'kodeModel'  => $item->kode_model,
                'namaSmh'    => $hargaRow?->nama_smh,
                'harga'      => $harga,
                'ditemukan'  => $harga !== null,
                'gudang'     => $item->gudang,
            ];
        }

        // Hitung sisa cover per unit
        foreach ($grouped as &$row) {
            $row['sisaCover']  = $row['plafonNilai'] !== null
                ? max(0, $row['plafonNilai'] - $row['totalNilai']) : null;
            $row['persentase'] = ($row['plafonNilai'] && $row['plafonNilai'] > 0)
                ? round($row['totalNilai'] / $row['plafonNilai'] * 100, 1) : null;
        }
        unset($row);

        // Agregat total semua unit dalam plan
        $units       = array_values($grouped);
        $totalUnit   = array_sum(array_column($units, 'totalUnit'));
        $ditemukan   = array_sum(array_column($units, 'ditemukan'));
        $tidakDitemukan = array_sum(array_column($units, 'tidakDitemukan'));
        $totalNilai  = array_sum(array_column($units, 'totalNilai'));
        $totalPlafon = array_sum(array_filter(array_column($units, 'plafonNilai'), fn($v) => $v !== null));

        return response()->json([
            'totalUnit'      => $totalUnit,
            'ditemukan'      => $ditemukan,
            'tidakDitemukan' => $tidakDitemukan,
            'totalNilaiSmh'  => $totalNilai,
            'totalPlafon'    => $totalPlafon ?: null,
            'sisaTotal'      => $totalPlafon > 0 ? max(0, $totalPlafon - $totalNilai) : null,
            'persentaseTotal'=> $totalPlafon > 0 ? round($totalNilai / $totalPlafon * 100, 1) : null,
            'perUnit'        => $units,
        ]);
    }

    // ── GET /api/audit-detail/plafon/unit-list ────────────────────────────────
    // Daftar unit usaha + info plafon untuk reference (dropdown jika diperlukan)

    public function unitList(Request $request): JsonResponse
    {
        $planId  = $request->query('plan_audit_id');

        // Distinct gudang dari onhand plan ini
        $gudangList = [];
        if ($planId) {
            $gudangList = SmhOnhandItem::query()
                ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
                ->whereNotNull('gudang')
                ->distinct()
                ->pluck('gudang')
                ->map(fn($g) => strtoupper(trim($g)))
                ->filter()
                ->values()
                ->toArray();
        }

        $plafonMap = DbPlafon::all()->keyBy(fn($r) => strtoupper(trim($r->kode ?? '')));

        $units = DbUnitUsaha::orderBy('kode')->get()->map(function ($u) use ($gudangList, $plafonMap) {
            $kode   = strtoupper(trim($u->kode ?? ''));
            $plafon = $plafonMap[$kode] ?? null;
            return [
                'kode'        => $u->kode,
                'nama'        => $u->nama,
                'wilayah'     => $u->alamat,
                'plafonNilai' => $plafon ? (float) $plafon->nilai : null,
                'hasOnhand'   => in_array($kode, $gudangList, true),
            ];
        });

        return response()->json([
            'data'   => $units->values(),
            'total'  => $units->count(),
        ]);
    }
}
