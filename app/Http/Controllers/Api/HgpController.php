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
        $colSparepart = 0; // kolom nama sparepart
        $colQty       = 1; // kolom qty / saldo awal

        foreach ($rows as $row) {
            $c0 = trim((string)($row[0] ?? ''));
            $c1 = trim((string)($row[1] ?? ''));

            // Deteksi header: baris yang mengandung "sparepart" atau "nama"
            if (!$headerPassed) {
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'sparepart') || str_contains($lower, 'nama')) {
                        $colSparepart = $ci;
                    }
                    if (str_contains($lower, 'qty') || str_contains($lower, 'jumlah') || str_contains($lower, 'stock')) {
                        $colQty = $ci;
                    }
                    if (str_contains($lower, 'sparepart') || str_contains($lower, 'nama')) {
                        $headerPassed = true;
                    }
                }
                if ($headerPassed) continue;
                // Juga skip baris kosong atau judul
                if ($c0 === '' && $c1 === '') continue;
                // Jika tidak ada header ditemukan setelah 5 baris, anggap data langsung
                continue;
            }

            $sparepart = trim((string)($row[$colSparepart] ?? ''));
            $qty       = $this->n($row[$colQty] ?? 0);

            if ($sparepart === '') continue;

            $items[] = [
                'sparepart'   => $sparepart,
                'saldoAwal'   => $qty,
                'fisik'       => 0,
                'akhir'       => $qty,
                'selisih'     => 0,
                'keterangan'  => '',
                'tgl'         => date('Y-m-d'),
                'logScan'     => [],
            ];
        }

        // Fallback: jika header tidak ditemukan, coba parse langsung baris non-kosong
        if (empty($items)) {
            foreach ($rows as $row) {
                $c0 = trim((string)($row[0] ?? ''));
                $c1 = $this->n($row[1] ?? 0);
                if ($c0 === '' || is_numeric($c0)) continue;
                $items[] = [
                    'sparepart'  => $c0,
                    'saldoAwal'  => $c1,
                    'fisik'      => 0,
                    'akhir'      => $c1,
                    'selisih'    => 0,
                    'keterangan' => '',
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
