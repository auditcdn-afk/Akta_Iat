<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MengunciDataPemeriksaan;
use App\Http\Controllers\Concerns\PenyegaranRingkas;
use App\Http\Controllers\Concerns\MenjagaHasilPemeriksaan;
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
// sample-nya (30 item, atau 50 item untuk gudang WHS Part / WHS Unit), bukan seluruh
// data hasil parse (file sumbernya bisa 1000+ baris). Master data HET (lookupHet/
// batchHet) tetap memakai endpoint HgpController — itu data referensi bersama,
// tidak spesifik per tool.
class RsaHgpController extends Controller
{
    use RequiresAuditorAuditee;
    use MenjagaHasilPemeriksaan;
    use MengunciDataPemeriksaan;
    use PenyegaranRingkas;

    private const DEFAULT_SAMPLE_SIZE = 30;
    private const WHS_SAMPLE_SIZE = 50;

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->jawabanPemeriksaan(PemeriksaanRsaHgp::class, $request));
    }

    // Sama seperti HgpController::save() — payload diperiksa dulu supaya snapshot
    // lama tidak menghapus hasil scan yang lebih baru. Lihat MenjagaHasilPemeriksaan.
    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'rsa-hgp');
        $who    = $request->user()?->username ?? $request->user()?->email;
        $mode   = (string) $request->input('mode', 'merge');
        $items  = (array) $request->input('items', []);

        return $this->denganKunciPemeriksaan(PemeriksaanRsaHgp::class, $planId,
            function (?PemeriksaanRsaHgp $rec) use ($request, $planId, $who, $mode, $items) {
        $tersimpan = $rec?->items_json ?? [];

                if ($mode === 'import') {
                    $items = $this->bawaHasilPemeriksaan($items, $tersimpan);
                } elseif ($mode !== 'replace') {
                    $hilang = $this->pemeriksaanYangHilang($items, $tersimpan);
                    if ($hilang !== []) {
                        return response()->json([
                            'message' => 'Data di server sudah lebih baru dari yang ada di layar ini — '
                                . count($hilang) . ' item yang sudah diperiksa akan hilang kalau ditimpa. '
                                . 'Muat ulang tab RSA HGP dulu, hasil scan Anda yang belum terkirim tetap aman.',
                            'stale'   => true,
                            'noPart'  => array_slice($hilang, 0, 20),
                        ], 409);
                    }
                }

                $data = ['items_json' => $items, 'updated_by' => $who];
                if ($request->filled('totalDitemukan')) $data['total_ditemukan'] = (int) $request->input('totalDitemukan');
                if ($request->filled('sampleSize')) $data['sample_size'] = (int) $request->input('sampleSize');

                $rec = PemeriksaanRsaHgp::updateOrCreate(['plan_audit_id' => $planId], $data);
                if (!$rec->created_by) $rec->update(['created_by' => $who]);

                return response()->json(['message' => 'Data RSA HGP tersimpan.', 'data' => $this->dataDenganSidik($rec->fresh())]);
            });
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

        return $this->denganKunciPemeriksaan(PemeriksaanRsaHgp::class, $planId,
            function (?PemeriksaanRsaHgp $rec) use ($request, $planId, $noPart, $qty, $who) {
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
                // Browser menggabung scan beruntun untuk No. Part yang sama menjadi 1
                // request (lihat createScanIncrementQueue di audit-editor.js) dan mengirim
                // rincian tiap scan lewat "entries". Riwayatnya tetap dicatat satu per satu
                // supaya hitungan "Fisik Terscan" (= jumlah entri logScan) tidak menyusut
                // gara-gara penggabungan itu.
                $entries = array_values(array_filter((array) $request->input('entries', []), 'is_array'));
                // qty=0 tanpa entries dipakai saat auditor cuma mengedit WO/Keterangan inline
                // di tabel (bukan scan baru) — tidak menambah fisik & tidak mencatat logScan palsu.
                if ($entries !== []) {
                    // Tiap entri bawa id dari browser: kalau request-nya diulang karena
                    // jaringan gudang putus, entri yang sudah tercatat dilewati sehingga
                    // fisiknya tidak bertambah dua kali (lihat terapkanEntriScan).
                    $it = $this->terapkanEntriScan($it, $entries);
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
            });
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

        return $this->denganKunciPemeriksaan(PemeriksaanRsaHgp::class, $planId,
            function (?PemeriksaanRsaHgp $rec) use ($planId, $noPart, $nama, $who) {
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
            });
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

        // File "stock Pagi" dari sistem gudang tidak punya baris header sama sekali:
        // isinya langsung no part;nama;...;stok;...;harga;kode gudang. Jalur di atas
        // menuntut sel "AWAL"/"QTY"/"JUMLAH", jadi tidak satu pun barisnya terbaca —
        // sementara jalur cadangan di bawah malah memungut baris yang nomor partnya
        // kebetulan seluruhnya angka lalu membaca nama part sebagai no part dan stok
        // dari kolom yang tidak ada (jadi 0). Diam-diam salah, bukan gagal.
        $kolomStok = null;
        if (!$headerPassed) {
            [$items, $kolomStok] = $this->bacaDaftarStokTanpaHeader($rows);
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
            // Untuk file tanpa header, kolom stoknya ditebak dari isi datanya.
            // Nomornya ikut dikirim supaya auditor bisa langsung melihat kolom
            // mana yang dibaca — salah kolom pada file audit tidak boleh cuma
            // ketahuan belakangan saat angkanya sudah dipakai.
            'kolomStok'   => $kolomStok,
        ]);
    }

    /**
     * Baca daftar stok yang sama sekali tidak punya baris header.
     *
     * Bentuk yang ditangani: kolom pertama nomor part, kolom kedua nama part,
     * lalu sederet kolom angka. Contoh (export "stock Pagi", pemisah titik koma):
     *
     *     03512HDL000;HONDA DISC LOCK;1;302;0;302;119000;GTM
     *
     * Kolom stoknya dipilih dengan aturan: kolom angka PERTAMA setelah nama yang
     * nilainya tidak sama di semua baris. Kolom satuan/pengali yang isinya 1 terus
     * (dan kolom 0 terus) otomatis terlewati karena tidak pernah berubah, sedangkan
     * kolom harga tidak pernah terpilih karena letaknya di belakang kolom stok.
     *
     * @return array{0: list<array<string, mixed>>, 1: int|null} item dan nomor kolom stok (1-based)
     */
    private function bacaDaftarStokTanpaHeader(array $rows): array
    {
        $baris = [];
        foreach ($rows as $row) {
            $noPart = trim((string)($row[0] ?? ''));

            // Yang wajib hanya nomor partnya. Nama boleh kosong — di file gudang
            // sungguhan ada baris seperti "FUEL TANK STAND K;;1;3;0;3;0;GTM" yang
            // stoknya nyata; membuangnya berarti diam-diam mengurangi saldo yang
            // harus dipertanggungjawabkan auditor.
            if ($noPart === '') continue;
            if (count($row) < 3) continue;

            $baris[] = $row;
        }

        if (count($baris) < 2) {
            return [[], null];
        }

        $kolomStok = $this->tebakKolomStok($baris);
        if ($kolomStok === null) {
            return [[], null];
        }

        $items = [];
        foreach ($baris as $row) {
            $saldoAkhir = $this->n($row[$kolomStok] ?? 0);

            $items[] = [
                'noPart'     => trim((string)$row[0]),
                'sparepart'  => trim((string)($row[1] ?? '')) !== ''
                    ? trim((string)$row[1])
                    : trim((string)$row[0]),
                'saldoAkhir' => $saldoAkhir,
                'fisik'      => 0,
                'akhir'      => $saldoAkhir,
                'selisih'    => -$saldoAkhir,
                'keterangan' => '',
                'tgl'        => date('Y-m-d'),
                'logScan'    => [],
            ];
        }

        return [$items, $kolomStok + 1];
    }

    /** @param list<array<int, mixed>> $baris */
    private function tebakKolomStok(array $baris): ?int
    {
        $jumlahKolom = max(array_map('count', $baris));

        for ($ci = 2; $ci < $jumlahKolom; $ci++) {
            $nilai = [];
            foreach ($baris as $row) {
                $sel = trim((string)($row[$ci] ?? ''));
                if ($sel === '' || !is_numeric(str_replace([',', ' '], ['.', ''], $sel))) {
                    // Satu sel saja bukan angka dan kolom ini bukan kolom stok —
                    // lebih baik lanjut mencari daripada mengarang nilai nol.
                    continue 2;
                }
                $nilai[$sel] = true;
            }

            // Kolom yang isinya sama persis di seluruh baris adalah satuan/pengali
            // (mis. selalu "1") atau kolom mutasi yang memang kosong (selalu "0"),
            // bukan stok. Lewati, cari kolom berikutnya.
            if (count($nilai) > 1) {
                return $ci;
            }
        }

        return null;
    }

    // Tentukan ukuran sample RSA berdasarkan unit usaha pada plan audit ini:
    // 50 item untuk gudang (WHS Part / WHS Unit), 30 untuk lainnya (Cabang /
    // Bengkel / default).
    //
    // Tiga petunjuk dipakai berurutan, dari yang paling tepercaya:
    //   1. db_unit_usaha.jenis — master data, mis. "WHS PART";
    //   2. nama unit usahanya sendiri — "WHS Part KIM" sudah menyebut dirinya
    //      gudang, jadi sample-nya tetap 50 walau unitnya belum terdaftar di
    //      master data (sebelumnya kasus ini diam-diam jatuh ke 30);
    //   3. jenis auditnya — penyelamat terakhir kalau cabangnya pun kosong.
    //
    // Kata "WAREHOUSE" ikut dikenali karena jenis audit menyebutnya lengkap
    // ("Audit Warehouse PART"), sementara master data & nama unit memakai "WHS".
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

        $petunjuk = trim((string) ($unitUsaha?->jenis ?: ''));
        if ($petunjuk === '') {
            $petunjuk = trim((string) ($plan->cabang ?: $plan->jenis_audit ?: ''));
        }
        $petunjuk = strtoupper($petunjuk);

        return (str_contains($petunjuk, 'WHS') || str_contains($petunjuk, 'WAREHOUSE'))
            ? self::WHS_SAMPLE_SIZE
            : self::DEFAULT_SAMPLE_SIZE;
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
