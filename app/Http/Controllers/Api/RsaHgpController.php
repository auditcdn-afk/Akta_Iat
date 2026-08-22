<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

// RSA HGP & AHM Oils — sama seperti HgpController, tapi bertipe "Random Sampling
// Audit": saat import Excel, item yang disimpan/ditampilkan untuk discan HANYA
// sample-nya (30 item, atau 50 item untuk unit usaha ber-role WHS), bukan seluruh
// data hasil parse (file sumbernya bisa 1000+ baris). Master data HET (lookupHet/
// batchHet) tetap memakai endpoint HgpController — itu data referensi bersama,
// tidak spesifik per tool.
class RsaHgpController extends Controller
{
    use RequiresAuditorAuditee;

    private const DEFAULT_SAMPLE_SIZE = 30;
    private const WHS_SAMPLE_SIZE = 50;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanRsaHgp::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'rsa-hgp');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $data = ['items_json' => $request->input('items', []), 'updated_by' => $who];
        if ($request->filled('totalDitemukan')) $data['total_ditemukan'] = (int) $request->input('totalDitemukan');
        if ($request->filled('sampleSize')) $data['sample_size'] = (int) $request->input('sampleSize');

        $rec = PemeriksaanRsaHgp::updateOrCreate(['plan_audit_id' => $planId], $data);
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data RSA HGP tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Sama seperti HgpController::scanIncrement — kirim delta 1 item saja, bukan
    // seluruh array items, supaya payload dari alat scan tetap kecil.
    // qty default 0 (bukan 1): dipakai juga untuk update wo/keterangan/tgl SAJA
    // (tanpa scan baru) dari edit inline kolom tabel — lihat catatan di bawah.
    public function scanIncrement(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $noPart = trim((string) $request->input('noPart', ''));
        $qty    = (float) $request->input('qty', 0);
        $who    = $request->user()?->username ?? $request->user()?->email;

        if ($noPart === '') {
            return response()->json(['message' => 'No. Part wajib diisi.'], 422);
        }

        $rec = PemeriksaanRsaHgp::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data RSA HGP belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        $idx = null;
        foreach ($items as $i => $row) {
            if (strcasecmp(trim((string)($row['noPart'] ?? '')), $noPart) === 0) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return response()->json(['message' => "No. Part \"{$noPart}\" tidak ditemukan."], 404);
        }

        $it = $items[$idx];
        // qty=0 dipakai saat auditor cuma mengedit WO/Keterangan inline di tabel
        // (bukan scan baru) — tidak menambah fisik & tidak mencatat logScan palsu.
        if ($qty !== 0.0) {
            $it['fisik'] = $this->n($it['fisik'] ?? 0) + $qty;
            $it['logScan'] = is_array($it['logScan'] ?? null) ? $it['logScan'] : [];
            $it['logScan'][] = ['at' => now()->toIso8601String(), 'qty' => $qty];
        }
        // keterangan/tgl/wo opsional — dikirim dari form input manual & edit inline
        // tabel, tidak dikirim dari jalur scan barcode cepat. Cuma ditimpa kalau
        // memang dikirim, supaya scan barcode (yang tidak membawa field ini) tidak
        // ikut mengosongkan keterangan/wo yang sudah ada.
        if ($request->has('keterangan')) {
            $it['keterangan'] = (string) $request->input('keterangan');
        }
        if ($request->has('tgl')) {
            $it['tgl'] = (string) $request->input('tgl');
        }
        if ($request->has('wo')) {
            $it['wo'] = $this->n($request->input('wo'));
        }
        // Rumus sama dengan rsaHgpCalcItem() di frontend: WO ikut menambah fisik.
        $saldo = $this->n($it['saldoAkhir'] ?? 0);
        $total = $this->n($it['fisik'] ?? 0) + $this->n($it['wo'] ?? 0);
        $it['akhir']   = $saldo - $total;
        $it['selisih'] = $total - $saldo;
        $items[$idx] = $it;

        $rec->items_json  = $items;
        $rec->updated_by  = $who;
        $rec->save();

        return response()->json(['message' => 'OK', 'item' => $it, 'idx' => $idx]);
    }

    // Tambah 1 No. Part manual (tombol "+ Tambah Part Manual") lewat baca-ubah-simpan
    // di server — bukan push ke array lokal browser lalu kirim ulang seluruh array
    // (rawan sama seperti masalah di scanIncrement: snapshot stale 1 auditor bisa
    // menimpa balik data auditor lain yang lebih baru).
    public function addItem(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $noPart = trim((string) $request->input('noPart', ''));
        $nama   = trim((string) $request->input('sparepart', ''));
        $who    = $request->user()?->username ?? $request->user()?->email;

        if ($noPart === '') {
            return response()->json(['message' => 'No. Part wajib diisi.'], 422);
        }

        $rec = PemeriksaanRsaHgp::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data RSA HGP belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        foreach ($items as $row) {
            if (strcasecmp(trim((string)($row['noPart'] ?? '')), $noPart) === 0) {
                return response()->json(['message' => "No. Part \"{$noPart}\" sudah ada dalam daftar."], 422);
            }
        }

        $newItem = [
            'noPart' => $noPart, 'sparepart' => $nama !== '' ? $nama : $noPart,
            'saldoAkhir' => 0, 'fisik' => 0, 'wo' => 0, 'akhir' => 0, 'selisih' => 0,
            'keterangan' => '', 'tgl' => now()->toDateString(), 'logScan' => [],
            '_manual' => true,
        ];
        $items[] = $newItem;

        $rec->items_json = $items;
        $rec->updated_by = $who;
        $rec->save();

        return response()->json(['message' => 'OK', 'item' => $newItem, 'idx' => count($items) - 1]);
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

        // Parsing sama persis dengan HgpController::parseExcel — lihat komentar di sana
        // untuk penjelasan posisi kolom relatif terhadap "AWAL" (merged-cell header).
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

            $c0 = trim((string)($row[0] ?? ''));
            if ($c0 === '') continue;
            if (!is_numeric($c0) && $c0 !== '') {
                continue;
            }

            $saldoAkhir = $this->n($row[$colAwal + 4] ?? 0);
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

        $totalFound = count($items);
        $sampleSize = $this->sampleSize($request->input('planAuditId') ?? $request->input('plan_audit_id'));
        [$sampledItems, $sampled] = $this->applySample($items, $sampleSize);

        return response()->json([
            'data'        => $sampledItems,
            'total'       => count($sampledItems),
            'totalFound'  => $totalFound,
            'sampleSize'  => $sampleSize,
            'sampled'     => $sampled,
        ]);
    }

    // Tentukan ukuran sample RSA berdasarkan role unit usaha pada plan audit ini:
    // 50 item untuk unit usaha ber-role WHS (WHS UNIT / WHS PART), 30 untuk lainnya
    // (Cabang / Bengkel / default). Cek db_unit_usaha.jenis dulu, fallback ke
    // plan_audits.jenis_audit kalau unit usahanya tidak ada di master data.
    private function sampleSize(mixed $planId): int
    {
        if (!$planId) {
            return self::DEFAULT_SAMPLE_SIZE;
        }

        $plan = PlanAudit::find($planId);
        if (!$plan) {
            return self::DEFAULT_SAMPLE_SIZE;
        }

        $unitUsaha = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        $jenis = strtoupper($unitUsaha?->jenis ?? $plan->jenis_audit ?? '');

        return str_contains($jenis, 'WHS') ? self::WHS_SAMPLE_SIZE : self::DEFAULT_SAMPLE_SIZE;
    }

    // Ambil sample acak sejumlah $sampleSize dari $items, dikembalikan dalam urutan
    // asli file (bukan urutan acak) supaya lebih mudah dibaca auditor saat scan.
    // Kalau jumlah item <= $sampleSize, kembalikan semuanya apa adanya (tidak perlu
    // disampling).
    private function applySample(array $items, int $sampleSize): array
    {
        $total = count($items);
        if ($total <= $sampleSize) {
            return [$items, false];
        }

        $keys = array_rand($items, $sampleSize);
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        sort($keys);

        $sampled = array_values(array_map(fn($k) => $items[$k], $keys));

        return [$sampled, true];
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }
}
