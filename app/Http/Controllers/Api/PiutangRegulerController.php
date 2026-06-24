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

        // Find header row (contains "CUSTOMER" or "NO. FAKTUR")
        $headerIdx = -1;
        $colMap    = [];
        foreach ($rows as $i => $row) {
            $upper = array_map(fn($c) => strtoupper(trim((string)$c)), $row);
            if (in_array('CUSTOMER', $upper) || in_array('NO. FAKTUR', $upper)) {
                $headerIdx = $i;
                foreach ($upper as $ci => $h) {
                    $colMap[$h] = $ci;
                }
                break;
            }
        }

        // Fallback: positional detection if no header row found
        if ($headerIdx < 0) {
            return $this->parsePositional($rows);
        }

        $find = fn(string ...$keys) => array_reduce($keys, fn($carry, $k) => $carry !== null ? $carry : ($colMap[$k] ?? null), null);

        $iNo       = $find('NO', 'NO.', '#');
        $iCust     = $find('CUSTOMER', 'NAMA CUSTOMER', 'NAMA');
        $iFaktur   = $find('NO. FAKTUR', 'NO FAKTUR', 'FAKTUR');
        $iTgl      = $find('TANGGAL', 'TGL', 'TANGGAL FAKTUR');
        $iType     = $find('TYPE', 'TIPE');
        $iSaldoAwal = $find('SALDO AWAL', 'SALDOAWAL');
        $iPokok    = $find('POKOK');
        $iPpn      = $find('PPN');
        $iLain2    = $find('LAIN2', 'LAIN-LAIN', 'LAINNYA');
        $iNoKwit   = $find('NO. KWIT', 'NO KWIT', 'NO.KWIT', 'NOKWIT');
        $iTglKredit = $find('TGL KREDIT', 'TGL. KREDIT', 'TANGGAL KREDIT');
        $iPembayaran = $find('PEMBAYARAN');
        $iSaldoAkhir = $find('SALDO AKHIR', 'SALDOAKHIR');
        $iBelumJto  = $find('BELUM JTO', 'BELUM JATUH TEMPO', 'BELUMJTO');
        $iTung15    = $find('1-5', 'TUNGGAKAN 1-5');
        $iTung630   = $find('6-30', 'TUNGGAKAN 6-30');
        $iTung3160  = $find('31-60', 'TUNGGAKAN 31-60');
        $iTung60    = $find('>60', 'TUNGGAKAN >60', 'TUNGGAKAN>60');

        $items = [];
        foreach (array_slice($rows, $headerIdx + 1) as $row) {
            $cust = trim((string)($iCust !== null ? $row[$iCust] : ($row[1] ?? '')));
            if ($cust === '') continue;
            if (strtolower($cust) === 'total' || strtolower($cust) === 'grand total') continue;

            $items[] = [
                'customer'    => $cust,
                'noFaktur'    => trim((string)($iFaktur !== null ? $row[$iFaktur] : ($row[2] ?? ''))),
                'tanggal'     => $this->toDateStr($iTgl !== null ? $row[$iTgl] : ($row[3] ?? null)),
                'type'        => trim((string)($iType !== null ? $row[$iType] : ($row[4] ?? ''))),
                'saldoAwal'   => $this->parseNum($iSaldoAwal !== null ? $row[$iSaldoAwal] : ($row[5] ?? null)),
                'pokok'       => $this->parseNum($iPokok !== null ? $row[$iPokok] : ($row[6] ?? null)),
                'ppn'         => $this->parseNum($iPpn !== null ? $row[$iPpn] : ($row[7] ?? null)),
                'lain2'       => $this->parseNum($iLain2 !== null ? $row[$iLain2] : ($row[8] ?? null)),
                'noKwit'      => trim((string)($iNoKwit !== null ? $row[$iNoKwit] : ($row[9] ?? ''))),
                'tglKredit'   => $this->toDateStr($iTglKredit !== null ? $row[$iTglKredit] : ($row[10] ?? null)),
                'pembayaran'  => $this->parseNum($iPembayaran !== null ? $row[$iPembayaran] : ($row[11] ?? null)),
                'saldoAkhir'  => $this->parseNum($iSaldoAkhir !== null ? $row[$iSaldoAkhir] : ($row[12] ?? null)),
                'belumJto'    => $this->parseNum($iBelumJto !== null ? $row[$iBelumJto] : ($row[13] ?? null)),
                'tung15'      => $this->parseNum($iTung15 !== null ? $row[$iTung15] : ($row[14] ?? null)),
                'tung630'     => $this->parseNum($iTung630 !== null ? $row[$iTung630] : ($row[15] ?? null)),
                'tung3160'    => $this->parseNum($iTung3160 !== null ? $row[$iTung3160] : ($row[16] ?? null)),
                'tung60'      => $this->parseNum($iTung60 !== null ? $row[$iTung60] : ($row[17] ?? null)),
                'keterangan'  => '',
            ];
        }

        return response()->json(['data' => $items]);
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
                'customer'   => $cust,
                'noFaktur'   => trim((string)($row[2] ?? '')),
                'tanggal'    => $this->toDateStr($row[3] ?? null),
                'type'       => trim((string)($row[4] ?? '')),
                'saldoAwal'  => $saldo,
                'pokok'      => $this->parseNum($row[6] ?? null),
                'ppn'        => $this->parseNum($row[7] ?? null),
                'lain2'      => $this->parseNum($row[8] ?? null),
                'noKwit'     => trim((string)($row[9] ?? '')),
                'tglKredit'  => $this->toDateStr($row[10] ?? null),
                'pembayaran' => $this->parseNum($row[11] ?? null),
                'saldoAkhir' => $this->parseNum($row[12] ?? null),
                'belumJto'   => $this->parseNum($row[13] ?? null),
                'tung15'     => $this->parseNum($row[14] ?? null),
                'tung630'    => $this->parseNum($row[15] ?? null),
                'tung3160'   => $this->parseNum($row[16] ?? null),
                'tung60'     => $this->parseNum($row[17] ?? null),
                'keterangan' => '',
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
