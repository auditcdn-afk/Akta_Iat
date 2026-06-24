<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanCekFisik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class CekFisikController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanCekFisik::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanCekFisik::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['data_json' => $request->input('data', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
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

        // Detect column positions from header row containing "Cek Fisik"
        $cfCol = 9; $stujCol = 11; $fstnkCol = 13; // defaults from known layout
        $signOffset = 1; // sign is one col after quantity
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                if ($cell !== null && str_contains(strtolower((string)$cell), 'cek fisik')) {
                    $cfCol    = $ci;
                    $stujCol  = $ci + 2;
                    $fstnkCol = $ci + 4;
                    break 2;
                }
            }
        }

        $company       = '';
        $tglPemeriksaan = '';
        $saldoAwal     = ['tanggal' => '', 'cf' => 0, 'stuj' => 0, 'fstnk' => 0];
        $penerimaan    = [];
        $pengeluaran   = [];
        $saldoAkhir    = ['cf' => 0, 'stuj' => 0, 'fstnk' => 0];
        $fisik         = ['cf' => 0, 'stuj' => 0, 'fstnk' => 0];
        $selisih       = ['cf' => 0, 'stuj' => 0, 'fstnk' => 0];
        $headerPassed  = false;

        foreach ($rows as $i => $row) {
            $col0 = trim((string)($row[0] ?? ''));
            $col2 = trim((string)($row[2] ?? ''));

            // Company name (first non-empty row)
            if ($company === '' && $col0 !== '') { $company = $col0; continue; }

            // Title row
            if (str_contains(strtolower($col0), 'berita acara')) { continue; }

            // Tgl Pemeriksaan (serial date near title)
            if ($tglPemeriksaan === '' && is_numeric($col0) && (float)$col0 > 40000) {
                $tglPemeriksaan = $this->serialToDate((float)$col0);
                continue;
            }

            // Header row (has "Cek Fisik")
            if ($row[$cfCol] !== null && str_contains(strtolower((string)($row[$cfCol] ?? '')), 'cek fisik')) {
                $headerPassed = true;
                continue;
            }
            if (!$headerPassed) continue;

            $cfQty    = $this->n($row[$cfCol]    ?? null);
            $stujQty  = $this->n($row[$stujCol]  ?? null);
            $fstnkQty = $this->n($row[$fstnkCol] ?? null);
            $cfSign   = trim((string)($row[$cfCol + 1]   ?? ''));
            $stujSign = trim((string)($row[$stujCol + 1] ?? ''));

            // Skip rows with no quantities at all
            if ($cfQty === 0.0 && $stujQty === 0.0 && $fstnkQty === 0.0 && $col0 === '') continue;

            // "Selisih" row
            if (strtolower($col0) === 'selisih') {
                $selisih = ['cf' => $cfQty, 'stuj' => $stujQty, 'fstnk' => $fstnkQty];
                continue;
            }

            // Diperiksa / signature rows — stop parsing
            if (str_contains(strtolower($col0), 'diperiksa') || str_contains(strtolower($col0), 'diketahui')) break;

            // Saldo Awal: serial date in col0, no sign, quantities present
            if (is_numeric($col0) && (float)$col0 > 40000 && $cfSign !== '-' && $cfSign !== '+') {
                if ($saldoAwal['tanggal'] === '') {
                    $saldoAwal = [
                        'tanggal' => $this->serialToDate((float)$col0),
                        'cf' => $cfQty, 'stuj' => $stujQty, 'fstnk' => $fstnkQty,
                    ];
                }
                continue;
            }

            // Subtotal row (col0 empty, no sign)
            if ($col0 === '' && $cfSign !== '-' && $cfSign !== '+' && ($cfQty > 0 || $stujQty > 0)) {
                // just a running total, skip
                continue;
            }

            // Period header rows (serial dates with nothing else meaningful)
            if (is_numeric($col0) && (float)$col0 > 40000 && $cfQty === 0.0 && $stujQty === 0.0) {
                continue;
            }

            // Transaction rows: col0 starts with "- "
            if (str_starts_with($col0, '- ')) {
                $desc = ltrim(substr($col0, 2));
                // Is it a date? → penerimaan if + sign
                if (preg_match('#^\d{2}[/-]\d{2}[/-]\d{4}#', $desc)) {
                    if ($cfSign === '+' || $stujSign === '+') {
                        $penerimaan[] = [
                            'tanggal'    => $this->normDate($desc),
                            'noDokumen'  => $col2,
                            'cf'         => $cfQty,
                            'stuj'       => $stujQty,
                            'fstnk'      => $fstnkQty,
                        ];
                    } else {
                        // Saldo fisik check row (date + "-" sign)
                        $fisik = ['cf' => $cfQty, 'stuj' => $stujQty, 'fstnk' => $fstnkQty];
                    }
                } else {
                    // Document number row → pengeluaran
                    $pengeluaran[] = [
                        'noDokumen' => $desc ?: $col2,
                        'cf'        => $cfQty,
                        'stuj'      => $stujQty,
                        'fstnk'     => $fstnkQty,
                    ];
                }
                continue;
            }

            // Saldo Akhir: serial date with quantities, sign "-" (fisik check) or no sign (system)
            if (is_numeric($col0) && (float)$col0 > 40000 && ($cfQty > 0 || $stujQty > 0)) {
                if ($cfSign === '-' || $stujSign === '-') {
                    $fisik = ['cf' => $cfQty, 'stuj' => $stujQty, 'fstnk' => $fstnkQty];
                } else {
                    $saldoAkhir = ['cf' => $cfQty, 'stuj' => $stujQty, 'fstnk' => $fstnkQty];
                }
            }
        }

        // If saldoAkhir still zero but fisik has values, use pre-fisik subtotal
        if ($saldoAkhir['cf'] === 0.0 && $saldoAkhir['stuj'] === 0.0) {
            $saldoAkhir = $fisik;
        }

        $data = compact('company', 'tglPemeriksaan', 'saldoAwal', 'penerimaan', 'pengeluaran', 'saldoAkhir', 'fisik', 'selisih');

        return response()->json(['data' => $data]);
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }

    private function serialToDate(float $serial): string
    {
        return date('Y-m-d', (int)(($serial - 25569) * 86400));
    }

    private function normDate(string $val): string
    {
        if (preg_match('#^(\d{2})[-/](\d{2})[-/](\d{4})$#', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return $val;
    }
}
