<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanPiutangReguler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class PiutangRegulerController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanPiutangReguler::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanPiutangReguler::updateOrCreate(
            ['plan_audit_id' => $planId],
            [
                'piutang_json' => $request->input('piutang', []),
                'updated_by'   => $who,
            ]
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

        // Anchor on the "Saldo Awal" header cell. The report uses a two-row
        // merged header with inconsistent labels, so positional mapping
        // relative to "Saldo Awal" is far more reliable than name matching.
        // Layout: No | Customer | No.Faktur | TGL | Type | SALDO AWAL | Pokok |
        //   PPN | Lain2 | No.Kwit | Tg | Pembayaran | Saldo Akhir | Belum JTO |
        //   1-5 | 6-30 | 31-60 | >60 | Giro Gantung/SPK | Keterangan
        $norm = fn($v) => strtoupper(preg_replace('/\s+/', '', (string)$v));

        // Locate the header by finding the "Saldo Awal" cell. If that exact
        // label isn't present, anchor on the "Customer" cell instead (the
        // Saldo Awal column sits 4 cells to its right in this report layout).
        $saldoCol  = null;
        $headerIdx = -1;
        foreach ($rows as $i => $row) {
            foreach ($row as $ci => $cell) {
                $n = $norm($cell);
                if (str_contains($n, 'SALDOAWAL')) {
                    $saldoCol  = $ci;
                    $headerIdx = $i;
                    break 2;
                }
            }
        }
        if ($saldoCol === null) {
            foreach ($rows as $i => $row) {
                foreach ($row as $ci => $cell) {
                    if ($norm($cell) === 'CUSTOMER') {
                        $saldoCol  = $ci + 4;
                        $headerIdx = $i;
                        break 2;
                    }
                }
            }
        }

        if ($saldoCol === null) {
            // Fall back to positional layout assuming the standard column order.
            return $this->parsePositional($rows);
        }

        $c = fn(int $offset) => $saldoCol + $offset;
        $items = [];
        foreach (array_slice($rows, $headerIdx + 1) as $row) {
            $cust = trim((string)($row[$c(-4)] ?? ''));
            if ($cust === '') continue;
            $lc = strtolower($cust);
            if (in_array($lc, ['customer', 'total', 'grand total'], true)) continue;

            // A valid data row has a numeric saldo awal or a non-empty faktur.
            $saldoAwal = $this->parseNum($row[$c(0)] ?? null);
            $noFaktur  = trim((string)($row[$c(-3)] ?? ''));
            if ($saldoAwal === 0.0 && $noFaktur === '') continue;

            $items[] = [
                'customer'    => $cust,
                'noFaktur'    => $noFaktur,
                'tanggal'     => $this->toDateStr($row[$c(-2)] ?? null),
                'type'        => trim((string)($row[$c(-1)] ?? '')),
                'saldoAwal'   => $saldoAwal,
                'pokok'       => $this->parseNum($row[$c(1)] ?? null),
                'ppn'         => $this->parseNum($row[$c(2)] ?? null),
                'lain2'       => $this->parseNum($row[$c(3)] ?? null),
                'noKwit'      => trim((string)($row[$c(4)] ?? '')),
                'tglKredit'   => $this->toDateStr($row[$c(5)] ?? null),
                'pembayaran'  => $this->parseNum($row[$c(6)] ?? null),
                'saldoAkhir'  => $this->parseNum($row[$c(7)] ?? null),
                'belumJto'    => $this->parseNum($row[$c(8)] ?? null),
                'tung15'      => $this->parseNum($row[$c(9)] ?? null),
                'tung630'     => $this->parseNum($row[$c(10)] ?? null),
                'tung3160'    => $this->parseNum($row[$c(11)] ?? null),
                'tung60'      => $this->parseNum($row[$c(12)] ?? null),
                'giroGantung' => trim((string)($row[$c(13)] ?? '')),
                'keterangan'  => trim((string)($row[$c(14)] ?? '')),
            ];
        }

        $resp = ['data' => $items];
        if (empty($items)) {
            // Diagnostics so we can see exactly what the parser detected.
            $resp['debug'] = [
                'saldoCol'  => $saldoCol,
                'headerIdx' => $headerIdx,
                'totalRows' => count($rows),
                'headerRow' => array_slice($rows[$headerIdx] ?? [], 0, 22),
                'firstDataRows' => array_map(
                    fn($r) => array_slice($r, 0, 22),
                    array_slice($rows, $headerIdx + 1, 5)
                ),
            ];
        }
        return response()->json($resp);
    }

    private function parsePositional(array $rows): JsonResponse
    {
        // Try to detect data rows: row where col[1] is non-empty text (customer name)
        // and col[5] is a large number (saldo awal)
        $items = [];
        foreach ($rows as $row) {
            $cust = trim((string)($row[1] ?? ''));
            if ($cust === '' || is_numeric($cust)) continue;
            if (strtolower($cust) === 'customer' || strtolower($cust) === 'total') continue;
            $saldo = $this->parseNum($row[5] ?? null);
            if ($saldo <= 0) continue;

            $items[] = [
                'customer'    => $cust,
                'noFaktur'    => trim((string)($row[2] ?? '')),
                'tanggal'     => $this->toDateStr($row[3] ?? null),
                'type'        => trim((string)($row[4] ?? '')),
                'saldoAwal'   => $saldo,
                'pokok'       => $this->parseNum($row[6] ?? null),
                'ppn'         => $this->parseNum($row[7] ?? null),
                'lain2'       => $this->parseNum($row[8] ?? null),
                'noKwit'      => trim((string)($row[9] ?? '')),
                'tglKredit'   => $this->toDateStr($row[10] ?? null),
                'pembayaran'  => $this->parseNum($row[11] ?? null),
                'saldoAkhir'  => $this->parseNum($row[12] ?? null),
                'belumJto'    => $this->parseNum($row[13] ?? null),
                'tung15'      => $this->parseNum($row[14] ?? null),
                'tung630'     => $this->parseNum($row[15] ?? null),
                'tung3160'    => $this->parseNum($row[16] ?? null),
                'tung60'      => $this->parseNum($row[17] ?? null),
                'giroGantung' => trim((string)($row[18] ?? '')),
                'keterangan'  => trim((string)($row[19] ?? '')),
            ];
        }
        return response()->json(['data' => $items]);
    }

    private function parseNum(mixed $val): float
    {
        if ($val === null || $val === '') return 0;
        if (is_int($val) || is_float($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.]/', '', (string)$val);
        return $clean === '' ? 0 : (float)$clean;
    }

    private function toDateStr(mixed $val): string
    {
        if ($val === null || $val === '') return '';
        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
        if (is_numeric($val)) {
            return date('Y-m-d', (int)(((float)$val - 25569) * 86400));
        }
        $s = trim((string)$val);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
