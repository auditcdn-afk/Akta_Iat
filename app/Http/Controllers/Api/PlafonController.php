<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbHargaSmh;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanSmh;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlafonController extends Controller
{
    // ── GET /api/audit-detail/plafon/units ───────────────────────────────────
    // Daftar unit usaha (dari db_unit_usaha) + info plafon + ringkasan SMH onhand

    public function units(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Semua unit usaha dari database
        $allUnits = DbUnitUsaha::orderBy('unit_usaha')->get();

        // Ambil distinct gudang yang ada di onhand plan ini
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

        // Plafon per kode
        $plafonMap = DbPlafon::all()->keyBy(fn($r) => strtoupper(trim($r->kode ?? '')));

        $result = $allUnits->map(function ($unit) use ($gudangList, $plafonMap) {
            $kode   = strtoupper(trim($unit->unit_usaha ?? ''));
            $plafon = $plafonMap[$kode] ?? null;
            return [
                'unitUsaha'   => $unit->unit_usaha,
                'wilayah'     => $unit->wilayah,
                'jenis'       => $unit->jenis,
                'plafonNilai' => $plafon?->nilai ?? null,
                'plafonNama'  => $plafon?->nama  ?? null,
                'hasOnhand'   => in_array($kode, $gudangList, true),
            ];
        });

        return response()->json([
            'data'         => $result->values(),
            'gudangOnhand' => $gudangList,
        ]);
    }

    // ── GET /api/audit-detail/plafon/analisa ─────────────────────────────────
    // Analisa plafon untuk satu unit usaha (gudang) atau semua unit dalam plan

    public function analisa(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $gudang = $request->query('gudang'); // null = semua unit dalam plan

        // Ambil onhand items
        $query = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));

        if ($gudang) {
            $query->where('gudang', $gudang);
        }

        $items = $query->get();

        // Harga SMH per kode_model
        $hargaMap = DbHargaSmh::all()->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));

        $totalUnit    = $items->count();
        $ditemukan    = 0;
        $tidakDitemukan = 0;
        $totalNilai   = 0.0;
        $detail       = [];

        foreach ($items as $item) {
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;

            if ($harga !== null) {
                $ditemukan++;
                $totalNilai += $harga;
            } else {
                $tidakDitemukan++;
            }

            $detail[] = [
                'noMesin'    => $item->no_mesin,
                'noRangka'   => $item->no_rangka,
                'kodeModel'  => $item->kode_model,
                'namaSmh'    => $hargaRow?->nama_smh,
                'harga'      => $harga,
                'ditemukan'  => $harga !== null,
                'gudang'     => $item->gudang,
                'statusFisik'=> $item->status_fisik,
            ];
        }

        // Plafon untuk unit ini
        $plafonNilai = null;
        $plafonNama  = null;
        if ($gudang) {
            $plafon = DbPlafon::whereRaw('UPPER(TRIM(kode)) = ?', [strtoupper(trim($gudang))])->first();
            $plafonNilai = $plafon?->nilai;
            $plafonNama  = $plafon?->nama;
        }

        return response()->json([
            'totalUnit'       => $totalUnit,
            'ditemukan'       => $ditemukan,
            'tidakDitemukan'  => $tidakDitemukan,
            'totalNilaiSmh'   => $totalNilai,
            'plafonNilai'     => $plafonNilai,
            'plafonNama'      => $plafonNama,
            'sisaCover'       => $plafonNilai !== null ? max(0, $plafonNilai - $totalNilai) : null,
            'persentase'      => ($plafonNilai && $plafonNilai > 0) ? round($totalNilai / $plafonNilai * 100, 1) : null,
            'detail'          => $detail,
        ]);
    }

    // ── GET /api/audit-detail/plafon/ringkasan ────────────────────────────────
    // Ringkasan semua gudang dalam plan: per-gudang total nilai + plafon

    public function ringkasan(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        $items = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
            ->get();

        $hargaMap  = DbHargaSmh::all()->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));
        $plafonMap = DbPlafon::all()->keyBy(fn($r) => strtoupper(trim($r->kode ?? '')));

        $grouped = [];
        foreach ($items as $item) {
            $gudang    = strtoupper(trim($item->gudang ?? '-'));
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;

            if (!isset($grouped[$gudang])) {
                $plafon = $plafonMap[$gudang] ?? null;
                $grouped[$gudang] = [
                    'gudang'        => $gudang,
                    'plafonNilai'   => $plafon?->nilai,
                    'totalUnit'     => 0,
                    'ditemukan'     => 0,
                    'totalNilai'    => 0.0,
                ];
            }
            $grouped[$gudang]['totalUnit']++;
            if ($harga !== null) {
                $grouped[$gudang]['ditemukan']++;
                $grouped[$gudang]['totalNilai'] += $harga;
            }
        }

        foreach ($grouped as &$row) {
            $row['sisaCover']   = $row['plafonNilai'] !== null ? max(0, $row['plafonNilai'] - $row['totalNilai']) : null;
            $row['persentase']  = ($row['plafonNilai'] && $row['plafonNilai'] > 0)
                ? round($row['totalNilai'] / $row['plafonNilai'] * 100, 1) : null;
        }
        unset($row);

        $totalNilai   = array_sum(array_column($grouped, 'totalNilai'));
        $totalPlafon  = array_sum(array_filter(array_column($grouped, 'plafonNilai'), fn($v) => $v !== null));

        return response()->json([
            'data'        => array_values($grouped),
            'totalNilai'  => $totalNilai,
            'totalPlafon' => $totalPlafon,
            'sisaTotal'   => max(0, $totalPlafon - $totalNilai),
            'persentase'  => ($totalPlafon > 0) ? round($totalNilai / $totalPlafon * 100, 1) : null,
        ]);
    }
}
