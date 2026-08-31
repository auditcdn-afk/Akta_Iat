<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MenjagaHasilPemeriksaan;
use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\PemeriksaanHga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class HgaController extends Controller
{
    use RequiresAuditorAuditee;
    use MenjagaHasilPemeriksaan;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanHga::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    // Simpan-penuh: menulis ULANG seluruh items_json dari apa yang dikirim browser.
    // Payload-nya diperiksa dulu terhadap yang sudah tersimpan supaya array lama
    // dari satu perangkat tidak menghapus hasil scan perangkat lain yang lebih
    // baru — aturan & mode-nya sama persis dengan HgpController::save(), lihat
    // MenjagaHasilPemeriksaan.
    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'hga');
        $who    = $request->user()?->username ?? $request->user()?->email;
        $mode   = (string) $request->input('mode', 'merge');
        $items  = (array) $request->input('items', []);

        $tersimpan = PemeriksaanHga::where('plan_audit_id', $planId)->first()?->items_json ?? [];

        if ($mode === 'import') {
            // Rumus HGA: total fisik = hasil scan + Fisik TTP, dan acuan saldonya
            // saldoPts kalau ada (lihat hgaCalcItem() di frontend & scanIncrement
            // di bawah). Berbeda dari HGP, jadi disuplai dari sini.
            $items = $this->bawaHasilPemeriksaan($items, $tersimpan, function (array $item): array {
                $totalFisik = $this->n($item['fisik'] ?? 0) + $this->n($item['fisikTtp'] ?? 0);
                $refSaldo   = array_key_exists('saldoPts', $item) && $item['saldoPts'] !== null
                    ? $this->n($item['saldoPts'])
                    : $this->n($item['saldoAkhir'] ?? ($item['saldoAwal'] ?? 0));
                $item['akhir']   = $refSaldo - $totalFisik;
                $item['selisih'] = $totalFisik - $refSaldo;

                return $item;
            });
        } elseif ($mode !== 'replace') {
            $hilang = $this->pemeriksaanYangHilang($items, $tersimpan);
            if ($hilang !== []) {
                return response()->json([
                    'message' => 'Data di server sudah lebih baru dari yang ada di layar ini — '
                        . count($hilang) . ' item yang sudah diperiksa akan hilang kalau ditimpa. '
                        . 'Muat ulang tab HGA dulu, hasil scan Anda yang belum terkirim tetap aman.',
                    'stale'   => true,
                    'noPart'  => array_slice($hilang, 0, 20),
                ], 409);
            }
        }

        $rec = PemeriksaanHga::updateOrCreate(
            ['plan_audit_id' => $planId],
            ['items_json' => $items, 'updated_by' => $who]
        );
        if (!$rec->created_by) $rec->update(['created_by' => $who]);

        return response()->json(['message' => 'Data HGA tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    // Simpan HANYA 1 item (delta) yang bertambah fisiknya dari 1 kali scan — sama seperti
    // HgpController::scanIncrement(), supaya payload dari alat scanner genggam tetap kecil
    // walau daftar onhand-nya ratusan/ribuan item.
    // qty default 0 (bukan 1): dipakai juga untuk update fisikTtp/keterangan/
    // keteranganTtp/tgl SAJA (tanpa scan baru) dari edit inline kolom tabel.
    public function scanIncrement(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $noPart = trim((string) $request->input('noPart', ''));
        $qty    = (float) $request->input('qty', 0);
        $who    = $request->user()?->username ?? $request->user()?->email;

        if ($noPart === '') {
            return response()->json(['message' => 'No. Part wajib diisi.'], 422);
        }

        $rec = PemeriksaanHga::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data HGA belum ada untuk plan audit ini.'], 422);
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
        // Browser menggabung scan beruntun untuk No. Part yang sama menjadi 1 request
        // dan mengirim rincian tiap scan lewat "entries". Tiap entri bawa id, jadi
        // request yang diulang (jaringan gudang putus) tidak menambah fisik dua kali
        // dan riwayat logScan tetap satu entri per scan — lihat terapkanEntriScan().
        $entries = array_values(array_filter((array) $request->input('entries', []), 'is_array'));
        // qty=0 tanpa entries dipakai saat auditor cuma mengedit Fisik TTP/Keterangan
        // inline di tabel (bukan scan baru) — tidak menambah fisik & tidak mencatat
        // logScan palsu.
        if ($entries !== []) {
            $it = $this->terapkanEntriScan($it, $entries);
        } elseif ($qty !== 0.0) {
            $it['fisik'] = $this->n($it['fisik'] ?? 0) + $qty;
            $it['logScan'] = is_array($it['logScan'] ?? null) ? $it['logScan'] : [];
            $it['logScan'][] = ['at' => now()->toIso8601String(), 'qty' => $qty];
        }
        // keterangan/keteranganTtp/tgl/fisikTtp opsional — dikirim dari form input
        // manual & edit inline tabel, tidak dikirim dari jalur scan barcode cepat.
        // Cuma ditimpa kalau memang dikirim, supaya scan barcode tidak ikut
        // mengosongkan field yang sudah ada.
        if ($request->has('keterangan')) {
            $it['keterangan'] = (string) $request->input('keterangan');
        }
        if ($request->has('keteranganTtp')) {
            $it['keteranganTtp'] = (string) $request->input('keteranganTtp');
        }
        if ($request->has('tgl')) {
            $it['tgl'] = (string) $request->input('tgl');
        }
        if ($request->has('fisikTtp')) {
            $it['fisikTtp'] = $this->n($request->input('fisikTtp'));
        }

        // Rumus sama dengan hgaCalcItem() di frontend: refSaldo pakai saldoPts kalau ada,
        // fallback ke saldoAkhir/saldoAwal; totalFisik = fisik scan + fisik TTP.
        $fisikTtp   = $this->n($it['fisikTtp'] ?? 0);
        $totalFisik = $this->n($it['fisik']) + $fisikTtp;
        $refSaldo   = array_key_exists('saldoPts', $it) && $it['saldoPts'] !== null
            ? $this->n($it['saldoPts'])
            : $this->n($it['saldoAkhir'] ?? ($it['saldoAwal'] ?? 0));
        $it['akhir']   = $refSaldo - $totalFisik;
        $it['selisih'] = $totalFisik - $refSaldo;

        $items[$idx] = $it;
        $rec->items_json = $items;
        $rec->updated_by = $who;
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

        $rec = PemeriksaanHga::where('plan_audit_id', $planId)->first();
        if (!$rec) {
            return response()->json(['message' => 'Data HGA belum ada untuk plan audit ini.'], 422);
        }

        $items = $rec->items_json ?? [];
        foreach ($items as $row) {
            if (strcasecmp(trim((string)($row['noPart'] ?? '')), $noPart) === 0) {
                return response()->json(['message' => "No. Part \"{$noPart}\" sudah ada dalam daftar."], 422);
            }
        }

        $newItem = [
            'noPart' => $noPart, 'sparepart' => $nama !== '' ? $nama : $noPart,
            'saldoAkhir' => 0, 'fisik' => 0, 'fisikTtp' => 0, 'akhir' => 0, 'selisih' => 0,
            'keterangan' => '', 'keteranganTtp' => '', 'tgl' => now()->toDateString(), 'logScan' => [],
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

        // Format HGA: header merged cell → posisi label ≠ posisi data
        // Header row: col[2]="Kode ACCS", col[4]="Keterangan", col[9]="Saldo Akhir"
        // Data row:   col[1]=noPart,       col[3]=nama,          col[10]=saldoAkhir
        // Deteksi header → noPart = colKode-1, nama = colKet-1, saldoAkhir = colSaldoAkhirHeader+1
        $items         = [];
        $headerPassed  = false;
        $colNoPart     = 1;
        $colNama       = 3;
        $colSaldoAkhir = 10;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                $hasHeader = false;
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'kode accs') || str_contains($lower, 'kode acc')) {
                        $colNoPart = max(0, $ci - 1); // merged cell: data satu kolom lebih kiri
                        $hasHeader = true;
                    }
                    if (str_contains($lower, 'keterangan') || str_contains($lower, 'nama barang')) {
                        $colNama = max(0, $ci - 1);
                    }
                    if (str_contains($lower, 'saldo akhir')) {
                        $colSaldoAkhir = $ci + 1; // merged cell: data satu kolom lebih kanan
                        $hasHeader = true;
                    }
                }
                if ($hasHeader) { $headerPassed = true; continue; }
                continue;
            }

            // Skip baris kosong (tidak ada no urut di col[0])
            $no = trim((string)($row[0] ?? ''));
            if (!is_numeric($no)) continue;

            $noPartRaw = trim((string)($row[$colNoPart] ?? ''));
            $namaRaw   = trim((string)($row[$colNama]   ?? ''));
            if ($noPartRaw === '' && $namaRaw === '') continue;

            $saldoAkhir = $this->n($row[$colSaldoAkhir] ?? 0);

            $items[] = [
                'noPart'     => $noPartRaw,
                'sparepart'  => $namaRaw !== '' ? $namaRaw : $noPartRaw,
                'saldoAkhir' => $saldoAkhir,
                'fisik'      => 0,
                'akhir'      => $saldoAkhir,
                'selisih'    => -$saldoAkhir,
                'keterangan' => '',
                'tgl'        => '',
                'logScan'    => [],
            ];
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
    }

    public function parsePts(Request $request): JsonResponse
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

        // Format PTS: [No, No HGA, Nama HGA, Qty] — header di baris pertama
        $items        = [];
        $headerPassed = false;
        $colNoPart    = 1;
        $colNama      = 2;
        $colQty       = 3;

        foreach ($rows as $row) {
            if (!$headerPassed) {
                foreach ($row as $ci => $cell) {
                    $lower = strtolower(trim((string)$cell));
                    if (str_contains($lower, 'no hga') || str_contains($lower, 'kode') || str_contains($lower, 'no. part')) {
                        $colNoPart = $ci;
                    }
                    if (str_contains($lower, 'nama')) {
                        $colNama = $ci;
                    }
                    if (str_contains($lower, 'qty') || str_contains($lower, 'jumlah') || str_contains($lower, 'saldo')) {
                        $colQty = $ci;
                    }
                }
                $headerPassed = true;
                continue;
            }

            $no = trim((string)($row[0] ?? ''));
            if (!is_numeric($no)) continue;

            $noPartRaw = trim((string)($row[$colNoPart] ?? ''));
            $namaRaw   = trim((string)($row[$colNama]   ?? ''));
            if ($noPartRaw === '') continue;

            $items[] = [
                'noPart'     => $noPartRaw,
                'sparepart'  => $namaRaw !== '' ? $namaRaw : $noPartRaw,
                'saldoAkhir' => $this->n($row[$colQty] ?? 0),
            ];
        }

        return response()->json(['data' => $items, 'total' => count($items)]);
    }

    private function n(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($clean === '' || $clean === '-') ? 0.0 : (float)$clean;
    }
}
