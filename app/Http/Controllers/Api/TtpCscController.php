<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\PemeriksaanTtpCsc;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class TtpCscController extends Controller
{
    use RequiresAuditorAuditee;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanTtpCsc::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'ttp-csc');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanTtpCsc::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data TTP CSC tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Simpan "Tanggal Portal" 1 baris (by index) — sekaligus hitung ulang
    // Selisih Tgl dan set default Keterangan di server (bukan browser),
    // supaya konsisten dipakai auditor mana pun. Baca-ubah-simpan langsung
    // di server (bukan kirim ulang seluruh array) — pola yang sama dengan
    // updateKeterangan di tool lain, supaya edit 1 baris tidak rawan
    // menimpa balik perubahan auditor lain.
    public function updateTanggalPortal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planAuditId'   => 'required|integer|exists:plan_audits,id',
            'index'         => 'required|integer|min:0',
            'tanggalPortal' => 'nullable|date',
        ]);
        $who = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanTtpCsc::where('plan_audit_id', $data['planAuditId'])->first();
        if (!$rec) {
            return response()->json(['message' => 'Data TTP CSC belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        if (!array_key_exists($data['index'], $items)) {
            return response()->json(['message' => 'Baris tidak ditemukan.'], 404);
        }

        $item = $items[$data['index']];
        $tanggalPortal = $data['tanggalPortal'] ?? '';
        $item['tanggalPortal'] = $tanggalPortal;

        if ($tanggalPortal === '' || empty($item['tanggal'])) {
            $item['selisihTgl'] = null;
            $item['keterangan'] = '';
        } else {
            $selisih = (int) abs(Carbon::parse($tanggalPortal)->startOfDay()->diffInDays(Carbon::parse($item['tanggal'])->startOfDay()));
            $item['selisihTgl'] = $selisih;
            $item['keterangan'] = $selisih === 0 ? 'Data Sesuai' : 'Selisih';
        }

        $items[$data['index']] = $item;
        $rec->update(['items_json' => $items, 'updated_by' => $who]);

        return response()->json(['message' => 'Tanggal Portal tersimpan.', 'item' => $item]);
    }

    // Override manual teks Keterangan (opsional) — dipisah dari
    // updateTanggalPortal supaya mengubah Tanggal Portal lagi nanti tidak
    // ikut menghapus catatan manual auditor kalau memang tidak diinginkan;
    // dalam alur normal, ubah Tanggal Portal akan menyetel ulang Keterangan
    // ke nilai otomatis (lihat komentar di atas), dan endpoint ini dipakai
    // sesudahnya kalau auditor ingin menulis catatan sendiri.
    public function updateKeterangan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planAuditId' => 'required|integer|exists:plan_audits,id',
            'index'       => 'required|integer|min:0',
            'keterangan'  => 'nullable|string|max:1000',
        ]);
        $who = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanTtpCsc::where('plan_audit_id', $data['planAuditId'])->first();
        if (!$rec) {
            return response()->json(['message' => 'Data TTP CSC belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        if (!array_key_exists($data['index'], $items)) {
            return response()->json(['message' => 'Baris tidak ditemukan.'], 404);
        }

        $items[$data['index']]['keterangan'] = $data['keterangan'] ?? '';
        $rec->update(['items_json' => $items, 'updated_by' => $who]);

        return response()->json(['message' => 'Keterangan tersimpan.']);
    }

    // Parser "LAPORAN TTP PANJAR" — ambil HANYA bagian "II. TTP SESUAI
    // PERIODE FILTER" (bukan bagian "I. TTP YANG BELUM SELESAI" yang sama
    // formatnya dan berada tepat di atasnya dalam sheet yang sama).
    //
    // Header 2-baris merged-cell menggeser posisi label dari posisi data
    // (pola yang sama seperti parser Piutang Reguler/Mutasi Pembelian),
    // tapi hanya untuk 2 kolom pertama (No & No.Reg) — dari kolom "No WO /
    // PRF" dan seterusnya label dan data SUDAH sejajar. Makanya anchor
    // dipasang di situ: kolom "No WO / PRF" (unik, tidak ambigu), lalu
    // kolom lain dihitung relatif terhadapnya:
    //   No=-5, No.Reg(TTP)=-4, Tanggal=-3, Nama=-2, Jenis=-1,
    //   No WO/PRF=0, Status=+1, Nilai(Penagihan)=+2, ...
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

        $norm = fn($v) => strtoupper(preg_replace('/\s+/', '', (string) $v));

        $foundSection = false;
        $anchorCol = null;
        $collecting = false;
        $items = [];

        foreach ($rows as $row) {
            $joined = implode('', array_map($norm, $row));

            if (str_contains($joined, 'TTPSESUAIPERIODEFILTER')) {
                $foundSection = true;
                $anchorCol = null;
                $collecting = false;
                continue;
            }

            if ($foundSection && $anchorCol === null) {
                foreach ($row as $ci => $cell) {
                    if (str_contains($norm($cell), 'NOWO')) {
                        $anchorCol = $ci;
                        $collecting = true;
                        break;
                    }
                }
                continue;
            }

            if ($collecting) {
                if (str_contains($joined, 'TOTAL')) {
                    $collecting = false;
                    continue;
                }

                $ttp = trim((string) ($row[$anchorCol - 4] ?? ''));
                if ($ttp === '') continue;

                $items[] = [
                    'no'            => (int) ($row[$anchorCol - 5] ?? 0),
                    'ttp'           => $ttp,
                    'tanggal'       => $this->excelDateToStr($row[$anchorCol - 3] ?? null),
                    'nama'          => trim((string) ($row[$anchorCol - 2] ?? '')),
                    'nilai'         => $this->n($row[$anchorCol + 2] ?? 0),
                    'tanggalPortal' => '',
                    'selisihTgl'    => null,
                    'keterangan'    => '',
                ];
            }
        }

        if (empty($items)) {
            return response()->json(['message' => 'Bagian "II. TTP SESUAI PERIODE FILTER" tidak ditemukan atau kosong di file ini.'], 422);
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
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
