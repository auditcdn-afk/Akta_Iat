<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanKwitansi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KwitansiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanKwitansi::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $payload = [
            'tgl_audit'     => $request->input('tglAudit') ?: null,
            'kwitansi_json' => $request->input('kwitansi', []),
            'updated_by'    => $who,
        ];

        $rec = PemeriksaanKwitansi::updateOrCreate(
            ['plan_audit_id' => $planId],
            $payload
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    public function parseExcel(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xls,xlsx,csv']);

        $path = $request->file('file')->store('tmp-kwitansi');
        $fullPath = storage_path('app/' . $path);

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false); // indexed from 0
        } finally {
            @unlink($fullPath);
        }

        $tglAuditStr = $request->input('tgl_audit');
        $tglAudit    = $tglAuditStr ? new \DateTime($tglAuditStr) : null;

        // Auto-detect the nilai column: column with most cells having value >= 1000.
        // Values may be numeric strings with thousands separators e.g. "15,834,000".
        $colCounts = [];
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                $n = $this->parseNum($cell);
                if ($n >= 1000) $colCounts[$ci] = ($colCounts[$ci] ?? 0) + 1;
            }
        }
        arsort($colCounts);
        $nilaiCol = count($colCounts) ? array_key_first($colCounts) : 8;

        $items         = [];
        $currentLeasing = 'LAINNYA';

        foreach ($rows as $row) {
            $col0 = trim((string)($row[0] ?? ''));
            if (!$col0) continue;

            $rowText = strtolower(implode(' ', array_map('strval', $row)));
            if (str_contains($rowText, 'sub total')) continue;
            if (str_starts_with(strtolower($col0), 'total')) continue;
            if (str_starts_with($col0, '*')) continue;

            $nilai = $this->parseNum($row[$nilaiCol] ?? null);

            // Real kwitansi values are in the millions; small numbers like a page
            // number ("Halaman : 1") are not data rows.
            if ($nilai >= 1000) {
                $tglRaw = $row[2] ?? null;
                $tglStr = $this->toDateStr($tglRaw);
                $diff   = null;
                if ($tglAudit && $tglStr) {
                    $d    = new \DateTime($tglStr);
                    $diff = (int)(($tglAudit->getTimestamp() - $d->getTimestamp()) / 86400);
                }
                $items[] = [
                    'leasing'       => $currentLeasing,
                    'noKwitansi'    => $col0,
                    'tglKwitansi'   => $tglStr,
                    'namaCustomer'  => trim((string)($row[3] ?? '')),
                    'noAr'          => trim((string)($row[5] ?? '')),
                    'noFaktur'      => trim((string)($row[6] ?? '')),
                    'nilaiKwitansi' => $nilai,
                    'diff'          => $diff,
                    'keterangan'    => '',
                    'fisik'         => false,
                ];
            } else {
                $nonEmpty = count(array_filter($row, fn($c) => $c !== null && $c !== ''));
                if ($nonEmpty <= 2) $currentLeasing = $col0;
            }
        }

        return response()->json(['data' => $items, 'nilaiCol' => $nilaiCol]);
    }

    private function parseNum(mixed $val): float
    {
        if ($val === null || $val === '') return 0;
        if (is_int($val) || is_float($val)) return (float)$val;
        // Strip everything except digits and decimal point (removes thousands separators)
        $clean = preg_replace('/[^0-9.]/', '', (string)$val);
        return $clean === '' ? 0 : (float)$clean;
    }

    private function toDateStr(mixed $val): string
    {
        if ($val === null || $val === '') return '';
        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
        if (is_numeric($val)) {
            // Excel serial date
            $unix = ((float)$val - 25569) * 86400;
            return date('Y-m-d', (int)$unix);
        }
        $s = trim((string)$val);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        // DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
