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

        // Cabang dari plan → dipakai untuk lookup plafon & unit usaha
        $plan   = PlanAudit::find($planId);
        $cabang = $plan?->cabang ?? '';
        $cabangSfx = $this->suffix3($cabang); // "SO ALB" → "ALB"

        // Semua onhand items untuk plan ini
        $items = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
            ->get();

        // Map harga SMH: kode_model → row
        $hargaMap = DbHargaSmh::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));

        // Cari plafon berdasarkan suffix cabang plan (CDN-ALB matches SO ALB via "ALB")
        $plafonRow = null;
        if ($cabangSfx) {
            foreach (DbPlafon::all() as $p) {
                foreach ([$p->nama, $p->kode] as $key) {
                    if ($this->suffix3($key) === $cabangSfx) {
                        $plafonRow = $p;
                        break 2;
                    }
                }
            }
        }
        $plafonNilai = $plafonRow ? (float) $plafonRow->nilai : null;

        // Cari info unit usaha (wilayah) berdasarkan suffix cabang
        $unitRow = null;
        if ($cabangSfx) {
            foreach (DbUnitUsaha::all() as $u) {
                foreach ([$u->nama, $u->kode] as $key) {
                    if ($this->suffix3($key) === $cabangSfx) {
                        $unitRow = $u;
                        break 2;
                    }
                }
            }
        }

        // Kelompokkan onhand per gudang (sub-unit), hitung nilai SMH
        $grouped = [];
        foreach ($items as $item) {
            $gudang    = trim($item->gudang ?? '-');
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;

            if (!isset($grouped[$gudang])) {
                $grouped[$gudang] = [
                    'gudang'         => $gudang,
                    'namaUnit'       => $gudang,
                    'totalUnit'      => 0,
                    'ditemukan'      => 0,
                    'tidakDitemukan' => 0,
                    'totalNilai'     => 0.0,
                    'detail'         => [],
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
                'noMesin'   => $item->no_mesin,
                'noRangka'  => $item->no_rangka,
                'kodeModel' => $item->kode_model,
                'namaSmh'   => $hargaRow?->nama_smh,
                'harga'     => $harga,
                'ditemukan' => $harga !== null,
                'gudang'    => $item->gudang,
            ];
        }

        $units          = array_values($grouped);
        $totalUnit      = array_sum(array_column($units, 'totalUnit'));
        $ditemukan      = array_sum(array_column($units, 'ditemukan'));
        $tidakDitemukan = array_sum(array_column($units, 'tidakDitemukan'));
        $totalNilai     = array_sum(array_column($units, 'totalNilai'));

        // Plafon berlaku untuk keseluruhan plan (bukan per gudang sub-unit)
        // Sisa cover & persentase per gudang dihitung proporsional dari plafon total
        foreach ($grouped as &$row) {
            $row['plafonNilai'] = $plafonNilai; // sama untuk semua sub-unit
            $row['sisaCover']   = $plafonNilai !== null ? max(0, $plafonNilai - $totalNilai) : null;
            $row['persentase']  = ($plafonNilai && $plafonNilai > 0)
                ? round($totalNilai / $plafonNilai * 100, 1) : null;
        }
        unset($row);

        return response()->json([
            'cabang'          => $cabang,
            'namaUnit'        => $unitRow?->nama ?? $cabang,
            'wilayah'         => $unitRow?->alamat ?? '—',
            'plafonNilai'     => $plafonNilai,
            'plafonNama'      => $plafonRow?->nama ?? null,
            'totalUnit'       => $totalUnit,
            'ditemukan'       => $ditemukan,
            'tidakDitemukan'  => $tidakDitemukan,
            'totalNilaiSmh'   => $totalNilai,
            'totalPlafon'     => $plafonNilai,
            'sisaTotal'       => $plafonNilai !== null ? max(0, $plafonNilai - $totalNilai) : null,
            'persentaseTotal' => ($plafonNilai && $plafonNilai > 0)
                ? round($totalNilai / $plafonNilai * 100, 1) : null,
            'perUnit'         => array_values($grouped),
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
