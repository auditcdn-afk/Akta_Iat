<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanHgp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class HgpController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanHgp::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanHgp::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data HGP tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    public function parseExcel(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
            return response()->json(['message' => 'File harus berformat .xls, .xlsx, atau .csv.'], 422);
        }

        $reader = match ($ext) {
            'xlsx' => new Xlsx(),
            'xls'  => new Xls(),
            'csv'  => new Csv(),
        };
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $items = [];
        $headerPassed = false;
        $colNoPart    = null;
        $colNama      = null;
        $colAwal      = null;
        $colKet       = null;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                $hasNama = false;
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'no part') || str_contains($lower, 'no_part') || str_contains($lower, 'part number') || $lower === 'kode') {
                        $colNoPart = $ci;
                    }
                    if (str_contains($lower, 'nama part') || str_contains($lower, 'nama_part') || $lower === 'nama' || str_contains($lower, 'sparepart') || str_contains($lower, 'nama barang')) {
                        $colNama = $ci;
                        $hasNama = true;
                    }
                    if ($lower === 'awal' || str_contains($lower, 'saldo awal') || $lower === 'qty' || str_contains($lower, 'jumlah') || str_contains($lower, 'stock') || str_contains($lower, 'stok')) {
                        $colAwal = $ci;
                    }
                    if ($colKet === null && ($lower === 'keterangan' || str_contains($lower, 'lokasi'))) {
                        $colKet = $ci;
                    }
                }
                if ($hasNama) { $headerPassed = true; continue; }
                continue;
            }

            $noPart = $colNoPart !== null ? trim((string)($row[$colNoPart] ?? '')) : '';
            $nama   = $colNama   !== null ? trim((string)($row[$colNama]   ?? '')) : '';
            $awal   = $colAwal   !== null ? $this->n($row[$colAwal] ?? 0) : $this->n($row[5] ?? 0);
            $ket    = $colKet    !== null ? trim((string)($row[$colKet] ?? '')) : '';

            if ($nama === '' && $noPart === '') continue;

            $sparepart = $nama !== '' ? $nama : $noPart;

            $items[] = [
                'noPart'     => $noPart,
                'sparepart'  => $sparepart,
                'saldoAwal'  => $awal,
                'fisik'      => 0,
                'akhir'      => 0,
                'selisih'    => -$awal,
                'keterangan' => $ket,
                'tgl'        => date('Y-m-d'),
                'logScan'    => [],
            ];
        }

        // Fallback: format onhand (col[1]=noPart, col[2]=nama, col[5]=awal, col[10]=ket)
        if (empty($items)) {
            foreach ($rows as $row) {
                $c1 = trim((string)($row[1] ?? ''));
                $c2 = trim((string)($row[2] ?? ''));
                if ($c2 === '' || is_numeric($c2)) continue;
                $awal = $this->n($row[5] ?? 0);
                $items[] = [
                    'noPart'     => $c1,
                    'sparepart'  => $c2,
                    'saldoAwal'  => $awal,
                    'fisik'      => 0,
                    'akhir'      => 0,
                    'selisih'    => -$awal,
                    'keterangan' => trim((string)($row[10] ?? '')),
                    'tgl'        => date('Y-m-d'),
                    'logScan'    => [],
                ];
            }
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }
}
