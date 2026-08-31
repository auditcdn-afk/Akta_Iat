<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbHet;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class HgpController extends Controller
{
    use RequiresAuditorAuditee;

    // Khusus jenis audit ini, tool "HGP & AHM Oils" (bukan tool terpisah
    // "RSA HGP & AHM Oils") ikut disampling acak 30 item saat import — item
    // lengkap dari file tetap ada di sistem (hanya HET DB dsb), tapi yang
    // dimuat untuk diperiksa hanya sample-nya. Jenis audit lain yang memakai
    // tool ini (Audit Full SO, Audit Kas + HGP & AHM Oils, dst) tidak
    // terpengaruh — tetap menampilkan seluruh item seperti sebelumnya.
    private const SAMPLED_JENIS_AUDIT = 'Audit Online Kas + HGP & AHM Oils';
    private const SAMPLE_SIZE = 30;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanHgp::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'hgp');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanHgp::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $request->input('items', []), 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data HGP tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Simpan HANYA 1 item (delta) yang bertambah fisiknya dari 1 kali scan, alih-alih
    // menerima & menulis ulang seluruh array items (items_json) seperti save(). Dipakai
    // dari alur scan barcode supaya payload yang dikirim dari alat scanner (mis. Honeywell
    // EDA52 di jaringan gudang yang bisa saja lemah) tetap kecil walau daftar onhand-nya
    // ratusan/ribuan item, bukan ikut membawa seluruh riwayat logScan semua item lain.
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

        $rec = PemeriksaanHgp::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data HGP belum ada untuk plan audit ini.'], 422);
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
        // Browser menggabung scan beruntun untuk No. Part yang sama menjadi 1
        // request (lihat createScanIncrementQueue di audit-editor.js) dan mengirim
        // rincian tiap scan lewat "entries". Riwayatnya tetap dicatat satu per satu
        // supaya hitungan "Fisik Terscan" (= jumlah entri logScan) tidak menyusut
        // gara-gara penggabungan itu.
        $entries = array_values(array_filter((array) $request->input('entries', []), 'is_array'));
        // qty=0 tanpa entries dipakai saat auditor cuma mengedit WO/Keterangan inline
        // di tabel (bukan scan baru) — tidak menambah fisik & tidak mencatat logScan palsu.
        if ($entries !== []) {
            $it['logScan'] = is_array($it['logScan'] ?? null) ? $it['logScan'] : [];
            foreach ($entries as $entry) {
                $q = $this->n($entry['qty'] ?? 0);
                if ($q === 0.0) continue;
                $it['fisik'] = $this->n($it['fisik'] ?? 0) + $q;
                $it['logScan'][] = ['at' => $this->scanTime($entry['at'] ?? null), 'qty' => $q];
            }
        } elseif ($qty !== 0.0) {
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
        // Rumus sama dengan hgpCalcItem() di frontend: WO ikut menambah fisik.
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

        $rec = PemeriksaanHgp::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data HGP belum ada untuk plan audit ini.'], 422);
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

        $totalFound = count($items);
        $planId     = $request->input('planAuditId') ?? $request->input('plan_audit_id');

        if ($this->shouldSample($planId)) {
            [$items, $sampled] = $this->applySample($items, self::SAMPLE_SIZE);

            return response()->json([
                'data'       => $items,
                'total'      => count($items),
                'totalFound' => $totalFound,
                'sampleSize' => self::SAMPLE_SIZE,
                'sampled'    => $sampled,
            ]);
        }

        return response()->json(['data' => $items, 'total' => $totalFound]);
    }

    private function shouldSample(mixed $planId): bool
    {
        if (!$planId) {
            return false;
        }

        return PlanAudit::where('id', $planId)
            ->where('jenis_audit', self::SAMPLED_JENIS_AUDIT)
            ->exists();
    }

    // Sama seperti RsaHgpController::applySample() — sample diambil acak tapi
    // dikembalikan dalam urutan asli file (bukan urutan acak) supaya lebih
    // mudah dibaca auditor saat scan. Kalau jumlah item <= sampleSize, tidak
    // perlu disampling, kembalikan semuanya apa adanya.
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

    // Waktu scan dari alat auditor dipakai apa adanya kalau bisa dibaca, supaya
    // riwayat menunjukkan kapan barang benar-benar discan — bukan kapan request
    // gabungannya sampai di server. Format asing jatuh ke waktu server.
    private function scanTime(mixed $at): string
    {
        if (is_string($at) && trim($at) !== '') {
            try {
                return Carbon::parse($at)->toIso8601String();
            } catch (\Throwable) {
                // biarkan jatuh ke waktu server di bawah
            }
        }

        return now()->toIso8601String();
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }
}
