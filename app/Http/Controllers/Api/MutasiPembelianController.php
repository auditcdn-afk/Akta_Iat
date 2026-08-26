<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\PemeriksaanMutasiPembelian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class MutasiPembelianController extends Controller
{
    use RequiresAuditorAuditee;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanMutasiPembelian::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'mutasi-pembelian');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanMutasiPembelian::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data Mutasi Pembelian tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Update HANYA kolom "keterangan" 1 baris (by index) — sama seperti
    // PiutangRegulerController::updateKeterangan — supaya edit keterangan 1
    // baris tidak kirim ulang seluruh array (bisa ratusan baris) dan tidak
    // rawan menimpa balik perubahan auditor lain yang lebih baru.
    public function updateKeterangan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planAuditId' => 'required|integer|exists:plan_audits,id',
            'index'       => 'required|integer|min:0',
            'keterangan'  => 'nullable|string|max:1000',
        ]);
        $who = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanMutasiPembelian::where('plan_audit_id', $data['planAuditId'])->first();
        if (!$rec) {
            return response()->json(['message' => 'Data Mutasi Pembelian belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        if (!array_key_exists($data['index'], $items)) {
            return response()->json(['message' => 'Baris tidak ditemukan.'], 404);
        }

        $items[$data['index']]['keterangan'] = $data['keterangan'] ?? '';
        $rec->update(['items_json' => $items, 'updated_by' => $who]);

        return response()->json(['message' => 'Keterangan tersimpan.']);
    }

    // Bandingkan 2 file: "Gudang" (patokan — laporan pembelian dari sistem
    // gudang, setiap barisnya WAJIB dicek) vs "Unit Usaha" (dipakai untuk
    // memverifikasi tiap baris Gudang: apakah kombinasi Kode Part+Qty+Nomor
    // Faktur-nya sudah tercatat diterima oleh unit usaha, lengkap dengan
    // lokasi penyimpanannya). Hasil perbandingan dikembalikan ke frontend
    // untuk direview dulu (belum langsung disimpan) — sama seperti
    // parseExcel() di tool lain, baru commit lewat save().
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'fileGudang'    => 'required|file',
            'fileUnitUsaha' => 'required|file',
        ]);

        $gudang    = $this->parseGudang($request->file('fileGudang'));
        $unitUsaha = $this->parseUnitUsaha($request->file('fileUnitUsaha'));

        if (empty($gudang)) {
            return response()->json(['message' => 'Tidak ada baris pembelian yang terbaca dari file Gudang.'], 422);
        }

        // Kode & Unit Usaha bersifat konstan untuk 1 file/plan (satu perusahaan),
        // bukan hasil per-baris dari pencocokan — diambil sekali dari baris
        // pertama yang mengisinya, lalu dipakai sama untuk SEMUA baris hasil
        // (termasuk yang "Belum Terima", supaya tetap jelas ini milik unit
        // usaha yang mana).
        $planKode = '';
        $planUnitUsaha = '';
        foreach ($unitUsaha as $uu) {
            if ($planKode === '' && $uu['kode'] !== '') $planKode = $uu['kode'];
            if ($planUnitUsaha === '' && $uu['unitUsaha'] !== '') $planUnitUsaha = $uu['unitUsaha'];
            if ($planKode !== '' && $planUnitUsaha !== '') break;
        }

        // Antrian per kunci (Kode Part|Qty|Nomor Faktur) — bukan peta 1 nilai,
        // supaya kombinasi yang sama persis muncul berkali-kali di file Unit
        // Usaha (mis. part sama, qty sama, no faktur sama tapi lokasi beda)
        // tetap dicocokkan satu-satu satu lokasi per baris Gudang, bukan
        // semuanya menempel ke lokasi baris pertama yang ditemukan.
        $queue = [];
        foreach ($unitUsaha as $uu) {
            $key = $this->matchKey($uu['kodePart'], $uu['qty'], $uu['nomorFaktur']);
            $queue[$key][] = $uu;
        }

        $items = [];
        foreach ($gudang as $g) {
            $key = $this->matchKey($g['kodePart'], $g['qty'], $g['nomorFaktur']);
            $found = null;
            if (!empty($queue[$key])) {
                $found = array_shift($queue[$key]);
            }

            $items[] = [
                'kodePart'     => $g['kodePart'],
                'namaPart'     => $g['namaBarang'],
                'qty'          => $g['qty'],
                'nomorFaktur'  => $g['nomorFaktur'],
                'tanggalFaktur'=> $g['tanggal'],
                'lokasi'       => $found['lokasi'] ?? '',
                'kode'         => $planKode,
                'unitUsaha'    => $planUnitUsaha,
                'keterangan'   => $found ? 'Sudah di terima dan di input' : 'Belum Terima',
                'matched'      => (bool) $found,
            ];
        }

        return response()->json([
            'data'        => $items,
            'total'       => count($items),
            'totalMatch'  => count(array_filter($items, fn($it) => $it['matched'])),
        ]);
    }

    private function matchKey(string $kodePart, float $qty, string $nomorFaktur): string
    {
        $kode = strtoupper(trim($kodePart));
        $no   = strtoupper(preg_replace('/\s+/', '', trim($nomorFaktur)));
        return $kode . '|' . number_format($qty, 4, '.', '') . '|' . $no;
    }

    private function loadSpreadsheet(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
            abort(422, 'File harus berformat .xls, .xlsx, atau .csv.');
        }

        $reader = match ($ext) {
            'xlsx' => new Xlsx(),
            'xls'  => new Xls(),
            'csv'  => new Csv(),
        };
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    // Parser file Gudang ("LAPORAN PEMBELIAN (Psch)" dsb) — laporan dengan
    // header merged-cell, sehingga posisi label ≠ posisi data (pola yang sama
    // dengan parser HGP/Piutang Reguler). Ditemukan lewat kolom "QTY" yang
    // posisinya stabil relatif terhadap kolom lain:
    //   TGL | NAMA SUPPLIER | NO.BUKTI | KODE PART+NAMA BARANG | QTY |
    //   HARGA BELI | DISCOUNT | NETTO | TGL.JTO
    // Offset relatif terhadap kolom QTY: tgl=-9, noBukti=-6, kodePart=-4, namaBarang=-3.
    private function parseGudang(UploadedFile $file): array
    {
        $rows = $this->loadSpreadsheet($file);

        $colQty = null;
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                if (strtoupper(trim((string) $cell)) === 'QTY') {
                    $colQty = $ci;
                    break 2;
                }
            }
        }
        if ($colQty === null) {
            abort(422, 'Kolom "QTY" tidak ditemukan di file Gudang — pastikan format laporan pembelian gudang sesuai.');
        }

        $c = fn(int $offset) => $colQty + $offset;
        $items = [];
        foreach ($rows as $row) {
            $tglRaw = $row[$c(-9)] ?? null;
            // Baris data selalu diawali tanggal (angka serial Excel). Baris
            // header/kosong/tanda-tangan di footer tidak numerik → dilewati.
            if (!is_numeric($tglRaw)) continue;

            $kodePart = trim((string) ($row[$c(-4)] ?? ''));
            $namaBarang = trim((string) ($row[$c(-3)] ?? ''));
            $noBukti = trim((string) ($row[$c(-6)] ?? ''));
            $qty = $this->n($row[$colQty] ?? 0);
            if ($kodePart === '' && $namaBarang === '') continue;

            $items[] = [
                'tanggal'    => $this->excelDateToStr($tglRaw),
                'kodePart'   => $kodePart,
                'namaBarang' => $namaBarang !== '' ? $namaBarang : $kodePart,
                'qty'        => $qty,
                'nomorFaktur'=> $noBukti,
            ];
        }

        return $items;
    }

    // Parser file Unit Usaha — header rapi 1 baris: Kode Part, Nama Part, Qty,
    // Nomor Faktur, Tanggal Faktur, Lokasi, Kode, Unit Usaha.
    private function parseUnitUsaha(UploadedFile $file): array
    {
        $rows = $this->loadSpreadsheet($file);

        $norm = fn($v) => strtolower(trim((string) $v));
        $col = ['kodePart' => null, 'namaPart' => null, 'qty' => null, 'nomorFaktur' => null, 'tanggalFaktur' => null, 'lokasi' => null, 'kode' => null, 'unitUsaha' => null];
        $headerIdx = -1;

        foreach ($rows as $i => $row) {
            foreach ($row as $ci => $cell) {
                $n = $norm($cell);
                if ($n === 'kode part') $col['kodePart'] = $ci;
                if ($n === 'nama part') $col['namaPart'] = $ci;
                if ($n === 'qty') $col['qty'] = $ci;
                if (str_contains($n, 'nomor faktur') || str_contains($n, 'no faktur') || str_contains($n, 'no. faktur')) $col['nomorFaktur'] = $ci;
                if (str_contains($n, 'tanggal faktur')) $col['tanggalFaktur'] = $ci;
                if ($n === 'lokasi') $col['lokasi'] = $ci;
                if ($n === 'kode') $col['kode'] = $ci;
                if (str_contains($n, 'unit usaha')) $col['unitUsaha'] = $ci;
            }
            if ($col['kodePart'] !== null && $col['qty'] !== null && $col['nomorFaktur'] !== null) {
                $headerIdx = $i;
                break;
            }
        }

        if ($headerIdx === -1) {
            abort(422, 'Header "Kode Part / Qty / Nomor Faktur" tidak ditemukan di file Unit Usaha.');
        }

        $items = [];
        foreach (array_slice($rows, $headerIdx + 1) as $row) {
            $kodePart = trim((string) ($row[$col['kodePart']] ?? ''));
            if ($kodePart === '') continue;

            $items[] = [
                'kodePart'    => $kodePart,
                'namaPart'    => $col['namaPart'] !== null ? trim((string) ($row[$col['namaPart']] ?? '')) : '',
                'qty'         => $this->n($row[$col['qty']] ?? 0),
                'nomorFaktur' => trim((string) ($row[$col['nomorFaktur']] ?? '')),
                'lokasi'      => $col['lokasi'] !== null ? trim((string) ($row[$col['lokasi']] ?? '')) : '',
                'kode'        => $col['kode'] !== null ? trim((string) ($row[$col['kode']] ?? '')) : '',
                'unitUsaha'   => $col['unitUsaha'] !== null ? trim((string) ($row[$col['unitUsaha']] ?? '')) : '',
            ];
        }

        return $items;
    }

    private function excelDateToStr(mixed $val): string
    {
        if (is_numeric($val)) {
            return date('Y-m-d', (int) ((((float) $val) - 25569) * 86400));
        }
        $s = trim((string) $val);
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float) $clean;
    }
}
