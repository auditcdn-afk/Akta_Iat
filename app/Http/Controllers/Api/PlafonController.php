<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbHargaSmh;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlafonController extends Controller
{
    // ── GET /api/audit-detail/plafon/analisa ─────────────────────────────────
    // Nama unit usaha bisa berbeda format antar tabel, misal:
    //   db_plafon.nama   = "CDN-ALB"
    //   db_unit_usaha.nama = "SO ALB"
    //   smh_onhand_items.gudang = "SO ALB"
    // Ketiganya dicocokkan lewat 3 huruf terakhir (suffix): "ALB"

    public function analisa(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Semua onhand items untuk plan ini
        $items = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
            ->get();

        // Map harga SMH: kode_model → row
        $hargaMap = DbHargaSmh::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));

        // Map unit usaha: suffix3(nama) → row (juga daftarkan suffix3(kode) sebagai fallback)
        $unitBySuffix = [];
        foreach (DbUnitUsaha::all() as $u) {
            foreach ([$u->nama, $u->kode] as $key) {
                $s = $this->suffix3($key);
                if ($s && !isset($unitBySuffix[$s])) $unitBySuffix[$s] = $u;
            }
        }

        // Map plafon: suffix3(nama) dan suffix3(kode) → row
        $plafonBySuffix = [];
        foreach (DbPlafon::all() as $p) {
            foreach ([$p->nama, $p->kode] as $key) {
                $s = $this->suffix3($key);
                if ($s && !isset($plafonBySuffix[$s])) $plafonBySuffix[$s] = $p;
            }
        }

        // Kelompokkan onhand per gudang, cocokkan via suffix3
        $grouped = [];
        foreach ($items as $item) {
            $gudang    = strtoupper(trim($item->gudang ?? '-'));
            $sfx       = $this->suffix3($gudang) ?: $gudang;
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;

            if (!isset($grouped[$sfx])) {
                $unitRow   = $unitBySuffix[$sfx]   ?? null;
                $plafonRow = $plafonBySuffix[$sfx]  ?? null;
                $grouped[$sfx] = [
                    'gudang'         => $gudang,
                    'suffix'         => $sfx,
                    'namaUnit'       => $unitRow?->nama ?? $gudang,
                    'wilayah'        => $unitRow?->alamat ?? '—',
                    'plafonNilai'    => $plafonRow ? (float) $plafonRow->nilai : null,
                    'plafonNama'     => $plafonRow?->nama ?? null,
                    'totalUnit'      => 0,
                    'ditemukan'      => 0,
                    'tidakDitemukan' => 0,
                    'totalNilai'     => 0.0,
                    'detail'         => [],
                ];
            }

            $grouped[$sfx]['totalUnit']++;
            if ($harga !== null) {
                $grouped[$sfx]['ditemukan']++;
                $grouped[$sfx]['totalNilai'] += $harga;
            } else {
                $grouped[$sfx]['tidakDitemukan']++;
            }

            $grouped[$sfx]['detail'][] = [
                'noMesin'   => $item->no_mesin,
                'noRangka'  => $item->no_rangka,
                'kodeModel' => $item->kode_model,
                'namaSmh'   => $hargaRow?->nama_smh,
                'harga'     => $harga,
                'ditemukan' => $harga !== null,
                'gudang'    => $item->gudang,
            ];
        }

        // Sisa cover & persentase per unit
        foreach ($grouped as &$row) {
            $row['sisaCover']  = $row['plafonNilai'] !== null
                ? max(0, $row['plafonNilai'] - $row['totalNilai']) : null;
            $row['persentase'] = ($row['plafonNilai'] && $row['plafonNilai'] > 0)
                ? round($row['totalNilai'] / $row['plafonNilai'] * 100, 1) : null;
        }
        unset($row);

        $units          = array_values($grouped);
        $totalUnit      = array_sum(array_column($units, 'totalUnit'));
        $ditemukan      = array_sum(array_column($units, 'ditemukan'));
        $tidakDitemukan = array_sum(array_column($units, 'tidakDitemukan'));
        $totalNilai     = array_sum(array_column($units, 'totalNilai'));
        $totalPlafon    = array_sum(array_filter(array_column($units, 'plafonNilai'), fn($v) => $v !== null));

        return response()->json([
            'totalUnit'       => $totalUnit,
            'ditemukan'       => $ditemukan,
            'tidakDitemukan'  => $tidakDitemukan,
            'totalNilaiSmh'   => $totalNilai,
            'totalPlafon'     => $totalPlafon ?: null,
            'sisaTotal'       => $totalPlafon > 0 ? max(0, $totalPlafon - $totalNilai) : null,
            'persentaseTotal' => $totalPlafon > 0 ? round($totalNilai / $totalPlafon * 100, 1) : null,
            'perUnit'         => $units,
        ]);
    }

    // ── GET /api/audit-detail/plafon/unit-list ────────────────────────────────

    public function unitList(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        $gudangSuffixes = [];
        if ($planId) {
            $gudangSuffixes = SmhOnhandItem::query()
                ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
                ->whereNotNull('gudang')
                ->distinct()
                ->pluck('gudang')
                ->map(fn($g) => $this->suffix3($g))
                ->filter()
                ->values()
                ->toArray();
        }

        $plafonBySuffix = [];
        foreach (DbPlafon::all() as $p) {
            foreach ([$p->nama, $p->kode] as $key) {
                $s = $this->suffix3($key);
                if ($s && !isset($plafonBySuffix[$s])) $plafonBySuffix[$s] = $p;
            }
        }

        $units = DbUnitUsaha::orderBy('nama')->get()->map(function ($u) use ($gudangSuffixes, $plafonBySuffix) {
            $sfx    = $this->suffix3($u->nama) ?: $this->suffix3($u->kode);
            $plafon = $plafonBySuffix[$sfx] ?? null;
            return [
                'kode'        => $u->kode,
                'nama'        => $u->nama,
                'wilayah'     => $u->alamat,
                'plafonNilai' => $plafon ? (float) $plafon->nilai : null,
                'hasOnhand'   => in_array($sfx, $gudangSuffixes, true),
            ];
        });

        return response()->json([
            'data'  => $units->values(),
            'total' => $units->count(),
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────
    // Ambil 3 huruf terakhir yang merupakan huruf/angka (abaikan spasi/tanda baca)
    // "CDN-ALB" → "ALB", "SO ALB" → "ALB", "SO TDB" → "TDB"

    private function suffix3(?string $str): ?string
    {
        if (!$str) return null;
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $str));
        return strlen($clean) >= 3 ? substr($clean, -3) : null;
    }
}
