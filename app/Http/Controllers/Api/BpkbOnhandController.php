<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BpkbOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BpkbOnhandController extends Controller
{
    // ── GET /api/audit-detail/bpkb?plan_audit_id= ────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $items  = BpkbOnhandItem::where('plan_audit_id', $planId)->orderBy('no_bpkb')->get();

        $total      = $items->count();
        $reg        = $items->where('jenis', 'REG')->count();
        $kds        = $items->where('jenis', 'KDS')->count();
        $sudahScan  = $items->where('sudah_scan', true)->count();
        $belumScan  = $items->where('sudah_scan', false)->count();
        $reg120     = $items->where('jenis', 'REG')->where('umur', '>', 120)->count();
        $reg120Pct  = $reg > 0 ? round($reg120 / $reg * 100, 2) : 0;

        return response()->json([
            'summary' => compact('total', 'reg', 'kds', 'sudahScan', 'belumScan', 'reg120', 'reg120Pct'),
            'items'   => $items->map->toAktaArray(),
        ]);
    }

    // ── GET /api/audit-detail/bpkb/search?q=&plan_audit_id= ─────────────────

    public function search(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $q      = trim($request->query('q', ''));
        if (strlen($q) < 3) return response()->json(['data' => []]);

        $rows = BpkbOnhandItem::where('plan_audit_id', $planId)
            ->where('no_bpkb', 'like', "%{$q}%")
            ->limit(10)->get();

        return response()->json(['data' => $rows->map->toAktaArray()]);
    }

    // ── POST /api/audit-detail/bpkb/upload ───────────────────────────────────

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file|mimes:xlsx,xls,csv',
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
        ]);

        $planId = $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email ?? null;

        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // Deteksi baris header
        $headerRow = null;
        $colMap    = [];
        foreach ($rows as $ri => $row) {
            $normalized = array_map(fn($v) => strtoupper(trim((string)($v ?? ''))), $row);
            foreach ($normalized as $ci => $cell) {
                if (str_contains($cell, 'NO BPKB') || str_contains($cell, 'NO.BPKB') || $cell === 'BPKB') {
                    $colMap['no_bpkb'] = $ci;
                }
                if (str_contains($cell, 'NO POLISI') || str_contains($cell, 'NOPOL')) {
                    $colMap['no_polisi'] = $ci;
                }
                if (str_contains($cell, 'TGL TERIMA') || str_contains($cell, 'TANGGAL TERIMA')) {
                    $colMap['tgl_terima'] = $ci;
                }
                if (str_contains($cell, 'NAMA PEMILIK') || $cell === 'PEMILIK') {
                    $colMap['nama_pemilik'] = $ci;
                }
                if (str_contains($cell, 'NO.TELEPON') || str_contains($cell, 'TELEPON') || str_contains($cell, 'NO TELEPON')) {
                    $colMap['no_telepon'] = $ci;
                }
                if (str_contains($cell, 'NO MESIN') || str_contains($cell, 'NO.MESIN')) {
                    $colMap['no_mesin'] = $ci;
                }
                if (str_contains($cell, 'NO RANGKA') || str_contains($cell, 'NO.RANGKA')) {
                    $colMap['no_rangka'] = $ci;
                }
                if ($cell === 'JENIS' || $cell === 'TYPE') {
                    $colMap['jenis'] = $ci;
                }
                if ($cell === 'UMUR' || str_contains($cell, 'UMUR')) {
                    $colMap['umur'] = $ci;
                }
            }
            if (!empty($colMap) && isset($colMap['no_bpkb'])) {
                $headerRow = $ri;
                break;
            }
        }

        if ($headerRow === null || !isset($colMap['no_bpkb'])) {
            return response()->json(['message' => 'Kolom NO BPKB tidak ditemukan di file Excel.'], 422);
        }

        $saved = 0;
        foreach ($rows as $ri => $row) {
            if ($ri <= $headerRow) continue;
            $noBpkb = trim((string)($row[$colMap['no_bpkb']] ?? ''));
            if ($noBpkb === '' || strtoupper($noBpkb) === 'NO BPKB') continue;

            // Parse nama pemilik & telepon (bisa satu kolom "NAMA & TELEPON")
            $namaPemilik = trim((string)($row[$colMap['nama_pemilik'] ?? -1] ?? ''));
            $noTelepon   = trim((string)($row[$colMap['no_telepon']   ?? -1] ?? ''));

            // Jika telepon kosong, coba pisah dari nama "Anton Riadi — 08123..."
            if ($noTelepon === '' && str_contains($namaPemilik, ' ')) {
                $parts = preg_split('/[\s\-\|]+/', $namaPemilik, 2);
                if (isset($parts[1]) && preg_match('/^[0-9+]/', $parts[1])) {
                    $namaPemilik = $parts[0];
                    $noTelepon   = $parts[1];
                }
            }

            // Parse tanggal
            $tglRaw   = $row[$colMap['tgl_terima'] ?? -1] ?? null;
            $tglTerima = null;
            if ($tglRaw) {
                if (is_numeric($tglRaw)) {
                    // Excel serial date
                    $tglTerima = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tglRaw)->format('Y-m-d');
                } else {
                    $parsed = date_create((string)$tglRaw);
                    if ($parsed) $tglTerima = $parsed->format('Y-m-d');
                }
            }

            $jenis = strtoupper(trim((string)($row[$colMap['jenis'] ?? -1] ?? '')));
            $umur  = (int)($row[$colMap['umur'] ?? -1] ?? 0);

            BpkbOnhandItem::updateOrCreate(
                ['plan_audit_id' => $planId, 'no_bpkb' => $noBpkb],
                [
                    'no_polisi'   => trim((string)($row[$colMap['no_polisi']   ?? -1] ?? '')) ?: null,
                    'tgl_terima'  => $tglTerima,
                    'nama_pemilik'=> $namaPemilik ?: null,
                    'no_telepon'  => $noTelepon ?: null,
                    'no_mesin'    => trim((string)($row[$colMap['no_mesin']    ?? -1] ?? '')) ?: null,
                    'no_rangka'   => trim((string)($row[$colMap['no_rangka']   ?? -1] ?? '')) ?: null,
                    'jenis'       => in_array($jenis, ['REG', 'KDS']) ? $jenis : null,
                    'umur'        => $umur > 0 ? $umur : null,
                    'created_by'  => $who,
                ]
            );
            $saved++;
        }

        return response()->json(['message' => "{$saved} data BPKB berhasil diimpor."]);
    }

    // ── POST /api/audit-detail/bpkb/scan ─────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $data   = $request->validate([
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
            'no_bpkb'       => 'required|string|max:100',
            'keterangan'    => 'nullable|string|max:255',
        ]);
        $planId = $data['plan_audit_id'];
        $noBpkb = trim($data['no_bpkb']);

        $item = BpkbOnhandItem::where('plan_audit_id', $planId)
            ->where('no_bpkb', $noBpkb)->first();

        if ($item) {
            // Ada di onhand — tandai sudah scan
            $item->update([
                'sudah_scan'  => true,
                'keterangan'  => $data['keterangan'] ?? $item->keterangan,
                'scan_at'     => now(),
            ]);
            return response()->json(['status' => 'found', 'data' => $item->fresh()->toAktaArray()]);
        }

        // Tidak ada di onhand — fisik diluar onhand
        $item = BpkbOnhandItem::updateOrCreate(
            ['plan_audit_id' => $planId, 'no_bpkb' => $noBpkb],
            [
                'sudah_scan'  => true,
                'keterangan'  => $data['keterangan'] ?? 'Fisik diluar onhand',
                'scan_at'     => now(),
                'jenis'       => 'LUAR',
            ]
        );
        return response()->json(['status' => 'outside', 'data' => $item->fresh()->toAktaArray()]);
    }

    // ── DELETE /api/audit-detail/bpkb/scan/{item} ────────────────────────────

    public function unscan(BpkbOnhandItem $bpkbOnhandItem): JsonResponse
    {
        if ($bpkbOnhandItem->jenis === 'LUAR') {
            $bpkbOnhandItem->delete();
        } else {
            $bpkbOnhandItem->update(['sudah_scan' => false, 'scan_at' => null, 'keterangan' => null]);
        }
        return response()->json(['message' => 'Scan dihapus.']);
    }

    // ── DELETE /api/audit-detail/bpkb/reset?plan_audit_id= ───────────────────

    public function reset(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        BpkbOnhandItem::where('plan_audit_id', $planId)->delete();
        return response()->json(['message' => 'Data BPKB dihapus.']);
    }
}
