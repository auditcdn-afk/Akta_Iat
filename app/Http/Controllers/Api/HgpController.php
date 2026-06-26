<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbHet;
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

        // Deteksi header: cari baris yang mengandung kolom "AWAL" (saldo awal)
        // Format onhand: header row punya merged cells, sehingga posisi data digeser -1 dari label
        // Header: col[2]="NO PART", col[4]="NAMA PART", col[5]="AWAL", col[10]="KETERANGAN"
        // Data:   col[1]=noPart,    col[2]=namapart,    col[5]=awal,   col[10]=ket
        // Rumus: colNoPart_data = colAwal - 4, colNama_data = colAwal - 3, colKet_data = colAwal + 5
        $items = [];
        $headerPassed = false;
        $colAwal      = null;
        $colNoPart    = null;
        $colNama      = null;
        $colKet       = null;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                $hasAwal = false;
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if ($lower === 'awal' || $lower === 'saldo awal' || $lower === 'qty' || str_contains($lower, 'jumlah')) {
                        $colAwal  = $ci;
                        $hasAwal  = true;
                    }
                    if ($lower === 'keterangan' || str_contains($lower, 'lokasi')) {
                        $colKet = $ci;
                    }
                }
                if ($hasAwal) {
                    // Tentukan kolom data berdasarkan posisi AWAL
                    // Coba deteksi no-part & nama dari header dulu
                    $colNoPart = null;
                    $colNama   = null;
                    foreach ($row as $ci => $cell) {
                        $lower = strtolower(trim((string)$cell));
                        if (str_contains($lower, 'no part') || str_contains($lower, 'no_part') || str_contains($lower, 'part number') || $lower === 'kode') {
                            $colNoPart = $ci;
                        }
                        if (str_contains($lower, 'nama part') || str_contains($lower, 'nama_part') || $lower === 'nama' || str_contains($lower, 'sparepart') || str_contains($lower, 'nama barang')) {
                            $colNama = $ci;
                        }
                    }
                    $headerPassed = true;
                    continue;
                }
                continue;
            }

            // Skip baris kosong
            $c0 = trim((string)($row[0] ?? ''));
            if ($c0 === '') continue;
            // Skip baris summary/total (col[0] bukan angka dan bukan data)
            if (!is_numeric($c0) && $c0 !== '') {
                // baris dengan text di col[0] biasanya bukan data part
                continue;
            }

            // Saldo baseline diambil dari kolom AKHIR (stok akhir sistem), bukan AWAL.
            // Kolom AKHIR berada di colAwal + 4 (AWAL, MASUK, KELUAR, ADJUST, AKHIR).
            $saldoAkhir = $this->n($row[$colAwal + 4] ?? 0);

            // Gunakan posisi relatif dari AWAL untuk menghindari masalah merged-cell di header.
            // Header merged-cell membuat posisi label ≠ posisi data aktual.
            // Posisi data: noPart = colAwal-4, nama = colAwal-3, ket = colAwal+5
            $noPartRaw = trim((string)($row[$colAwal - 4] ?? ''));
            $namaRaw   = trim((string)($row[$colAwal - 3] ?? ''));

            if ($noPartRaw === '' && $namaRaw === '') continue;

            $ket = $colKet !== null ? trim((string)($row[$colKet] ?? '')) : '';

            $items[] = [
                'noPart'     => $noPartRaw,
                'sparepart'  => $namaRaw !== '' ? $namaRaw : $noPartRaw,
                'saldoAkhir' => $saldoAkhir,
                'fisik'      => 0,
                'akhir'      => $saldoAkhir,
                'selisih'    => -$saldoAkhir,
                'keterangan' => $ket,
                'tgl'        => date('Y-m-d'),
                'logScan'    => [],
            ];
        }

        // Fallback: tidak ada header AWAL — coba parse langsung (col[1]=noPart, col[2]=nama, col[5]=awal)
        if (empty($items)) {
            foreach ($rows as $row) {
                if (!is_numeric(trim((string)($row[0] ?? '')))) continue;
                $c1 = trim((string)($row[1] ?? ''));
                $c2 = trim((string)($row[2] ?? ''));
                if ($c1 === '' && $c2 === '') continue;
                $saldoAkhir = $this->n($row[9] ?? 0);
                $items[] = [
                    'noPart'     => $c1,
                    'sparepart'  => $c2 !== '' ? $c2 : $c1,
                    'saldoAkhir' => $saldoAkhir,
                    'fisik'      => 0,
                    'akhir'      => $saldoAkhir,
                    'selisih'    => -$saldoAkhir,
                    'keterangan' => trim((string)($row[10] ?? '')),
                    'tgl'        => date('Y-m-d'),
                    'logScan'    => [],
                ];
            }
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
    }

    public function lookupHet(Request $request): JsonResponse
    {
        $kode = trim($request->query('kode', ''));
        if ($kode === '') return response()->json(['data' => null]);
        $row = DbHet::where('kode', $kode)->first();
        return response()->json(['data' => $row ? ['kode' => $row->kode, 'nama' => $row->nama, 'hargaHet' => $row->harga_het] : null]);
    }

    public function batchHet(Request $request): JsonResponse
    {
        $kodes = array_filter(array_map('trim', (array)$request->input('kodes', [])));
        if (empty($kodes)) return response()->json(['data' => []]);
        $rows = DbHet::whereIn('kode', $kodes)->get(['kode', 'nama', 'harga_het']);
        $map  = [];
        foreach ($rows as $r) {
            $map[$r->kode] = ['nama' => $r->nama, 'hargaHet' => $r->harga_het];
        }
        return response()->json(['data' => $map]);
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }
}
