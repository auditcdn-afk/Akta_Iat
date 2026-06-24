<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanPiutangCdn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class PiutangCdnController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanPiutangCdn::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanPiutangCdn::updateOrCreate(
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

        // Layout (0-indexed columns):
        //   0=No | 1=No.Kontrak | 2=Tanggal | 3=Nama Customer | 4=Saldo Piutang |
        //   5=Belum JTO | 6=Tung 1-5 | 7=Tung 6-30 | 8=Tung 31-60 | 9=Tung >60 |
        //   10=Analisa 0bln | 11=1bln | 12=2bln | 13=3bln | 14=4bln | 15=>5bln
        //
        // Data rows: col[0] is numeric (row number), col[1] has kontrak like "GZ001-22"

        $items = [];
        foreach ($rows as $row) {
            // Col[0] must be a positive integer (row number)
            $no = $row[0] ?? null;
            if ($no === null || $no === '' || !is_numeric($no) || (int)$no <= 0) continue;

            // Col[3] must be a non-empty non-numeric customer name
            $cust = trim((string)($row[3] ?? ''));
            if ($cust === '' || is_numeric($cust)) continue;

            $norm = strtolower(preg_replace('/\s+/', '', $cust));
            if (in_array($norm, ['namacustomer', 'total', 'grandtotal', 'subtotal'], true)) continue;

            $saldo = $this->parseNum($row[4] ?? null);
            if ($saldo === 0.0 && trim((string)($row[1] ?? '')) === '') continue;

            $items[] = [
                'noKontrak'   => trim((string)($row[1] ?? '')),
                'tanggal'     => $this->toDateStr($row[2] ?? null),
                'customer'    => $cust,
                'saldoPiutang'=> $saldo,
                'belumJto'    => $this->parseNum($row[5] ?? null),
                'tung15'      => $this->parseNum($row[6] ?? null),
                'tung630'     => $this->parseNum($row[7] ?? null),
                'tung3160'    => $this->parseNum($row[8] ?? null),
                'tung60'      => $this->parseNum($row[9] ?? null),
                'analisa0'    => $this->parseNum($row[10] ?? null),
                'analisa1'    => $this->parseNum($row[11] ?? null),
                'analisa2'    => $this->parseNum($row[12] ?? null),
                'analisa3'    => $this->parseNum($row[13] ?? null),
                'analisa4'    => $this->parseNum($row[14] ?? null),
                'analisa5'    => $this->parseNum($row[15] ?? null),
                'keterangan'  => '',
            ];
        }

        return response()->json(['data' => $items]);
    }

    private function parseNum(mixed $val): float
    {
        if ($val === null || $val === '') return 0;
        if (is_int($val) || is_float($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0 : (float)$clean;
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
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})#', $s, $m)) {
            $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
