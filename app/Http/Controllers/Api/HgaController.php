<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanHga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class HgaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanHga::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanHga::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data HGA tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
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

        // Format HGA: header merged cell → posisi label ≠ posisi data
        // Header row: col[2]="Kode ACCS", col[4]="Keterangan", col[9]="Saldo Akhir"
        // Data row:   col[1]=noPart,       col[3]=nama,          col[10]=saldoAkhir
        // Deteksi header → noPart = colKode-1, nama = colKet-1, saldoAkhir = colSaldoAkhirHeader+1
        $items         = [];
        $headerPassed  = false;
        $colNoPart     = 1;
        $colNama       = 3;
        $colSaldoAkhir = 10;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                $hasHeader = false;
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'kode accs') || str_contains($lower, 'kode acc')) {
                        $colNoPart = max(0, $ci - 1); // merged cell: data satu kolom lebih kiri
                        $hasHeader = true;
                    }
                    if (str_contains($lower, 'keterangan') || str_contains($lower, 'nama barang')) {
                        $colNama = max(0, $ci - 1);
                    }
                    if (str_contains($lower, 'saldo akhir')) {
                        $colSaldoAkhir = $ci + 1; // merged cell: data satu kolom lebih kanan
                        $hasHeader = true;
                    }
                }
                if ($hasHeader) { $headerPassed = true; continue; }
                continue;
            }

            // Skip baris kosong (tidak ada no urut di col[0])
            $no = trim((string)($row[0] ?? ''));
            if (!is_numeric($no)) continue;

            $noPartRaw = trim((string)($row[$colNoPart] ?? ''));
            $namaRaw   = trim((string)($row[$colNama]   ?? ''));
            if ($noPartRaw === '' && $namaRaw === '') continue;

            $saldoAkhir = $this->n($row[$colSaldoAkhir] ?? 0);

            $items[] = [
                'noPart'     => $noPartRaw,
                'sparepart'  => $namaRaw !== '' ? $namaRaw : $noPartRaw,
                'saldoAkhir' => $saldoAkhir,
                'fisik'      => 0,
                'akhir'      => $saldoAkhir,
                'selisih'    => -$saldoAkhir,
                'keterangan' => '',
                'tgl'        => '',
                'logScan'    => [],
            ];
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
    }

    public function parsePts(Request $request): JsonResponse
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

        // Format PTS: [No, No HGA, Nama HGA, Qty] — header di baris pertama
        $items        = [];
        $headerPassed = false;
        $colNoPart    = 1;
        $colNama      = 2;
        $colQty       = 3;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'no hga') || str_contains($lower, 'kode') || str_contains($lower, 'no. part')) {
                        $colNoPart = $ci;
                    }
                    if (str_contains($lower, 'nama')) {
                        $colNama = $ci;
                    }
                    if (str_contains($lower, 'qty') || str_contains($lower, 'jumlah') || str_contains($lower, 'saldo')) {
                        $colQty = $ci;
                    }
                }
                $headerPassed = true;
                continue;
            }

            $no = trim((string)($row[0] ?? ''));
            if (!is_numeric($no)) continue;

            $noPartRaw = trim((string)($row[$colNoPart] ?? ''));
            $namaRaw   = trim((string)($row[$colNama]   ?? ''));
            if ($noPartRaw === '') continue;

            $items[] = [
                'noPart'     => $noPartRaw,
                'sparepart'  => $namaRaw !== '' ? $namaRaw : $noPartRaw,
                'saldoAkhir' => $this->n($row[$colQty] ?? 0),
            ];
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
