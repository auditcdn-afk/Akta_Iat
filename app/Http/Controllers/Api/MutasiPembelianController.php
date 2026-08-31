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

    // Hapus HANYA 1 baris (by index) — baca-ubah-simpan langsung di server,
    // sama seperti updateKeterangan(), supaya tidak perlu (dan tidak rawan
    // menimpa balik) kirim ulang seluruh array dari memori browser.
    // array_values() setelah splice supaya indeks kembali rapat 0..n-1 —
    // frontend me-render ulang seluruh tabel dari respons ini sehingga
    // data-mp-idx di setiap baris selalu cocok dengan indeks di server.
    public function deleteItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planAuditId' => 'required|integer|exists:plan_audits,id',
            'index'       => 'required|integer|min:0',
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

        array_splice($items, $data['index'], 1);
        $items = array_values($items);
        $rec->update(['items_json' => $items, 'updated_by' => $who]);

        return response()->json(['message' => 'Baris dihapus.', 'data' => $items]);
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

    // Berkas laporan pembelian datang dalam tiga bentuk, dan bentuknya BERBEDA
    // antar cabang — bahkan sisi Gudang & Unit Usaha di satu cabang bisa saling
    // bertukar bentuk. Karena itu kedua sisi memakai pembaca yang sama dan
    // mencoba ketiganya berurutan, bukan memaksakan satu bentuk per sisi:
    //   1. header rapi   : Kode Part | Nama Part | Qty | Nomor Faktur | ...
    //   2. LAPORAN PEMBELIAN (Psch): header merged-cell, posisi data digeser
    //   3. tanpa header   : dikenali dari isi kolomnya
    private function parseGudang(UploadedFile $file): array
    {
        return array_map(fn(array $it) => [
            'tanggal'    => $it['tanggal'],
            'kodePart'   => $it['kodePart'],
            'namaBarang' => $it['namaPart'] !== '' ? $it['namaPart'] : $it['kodePart'],
            'qty'        => $it['qty'],
            'nomorFaktur'=> $it['nomorFaktur'],
        ], $this->bacaBerkasPembelian($this->loadSpreadsheet($file), 'Gudang'));
    }

    private function parseUnitUsaha(UploadedFile $file): array
    {
        return array_map(fn(array $it) => [
            'kodePart'    => $it['kodePart'],
            'namaPart'    => $it['namaPart'],
            'qty'         => $it['qty'],
            'nomorFaktur' => $it['nomorFaktur'],
            'lokasi'      => $it['lokasi'],
            'kode'        => $it['kode'],
            'unitUsaha'   => $it['unitUsaha'],
        ], $this->bacaBerkasPembelian($this->loadSpreadsheet($file), 'Unit Usaha'));
    }

    // Dicoba dari bentuk yang paling pasti (judul kolom eksplisit) ke yang paling
    // menebak (dikenali dari isi). Bentuk yang menghasilkan baris duluan dipakai;
    // "ketemu judulnya tapi tidak ada baris data" berarti tebakannya salah, jadi
    // lanjut ke bentuk berikutnya — bukan dianggap berkas kosong.
    private function bacaBerkasPembelian(array $rows, string $label): array
    {
        $items = $this->bacaHeaderRapi($rows);
        if (!empty($items)) return $items;

        $items = $this->bacaLaporanPembelian($rows);
        if (!empty($items)) return $items;

        return $this->bacaTanpaHeader($rows, $label);
    }

    // Bentuk 1 — header rapi dalam satu baris. Selain Kode Part/Qty/Nomor Faktur,
    // bentuk ini juga membawa Lokasi/Kode/Unit Usaha yang dipakai di hasil
    // perbandingan; bentuk lain tidak punya itu dan dibiarkan kosong.
    private function bacaHeaderRapi(array $rows): ?array
    {
        $norm = fn($v) => strtolower(trim((string) $v));
        $col = ['kodePart' => null, 'namaPart' => null, 'qty' => null, 'nomorFaktur' => null,
                'tanggalFaktur' => null, 'lokasi' => null, 'kode' => null, 'unitUsaha' => null];
        $headerIdx = -1;

        foreach ($rows as $i => $row) {
            foreach ($row as $ci => $cell) {
                $n = $norm($cell);
                if ($n === 'kode part') $col['kodePart'] = $ci;
                if ($n === 'nama part' || $n === 'nama barang') $col['namaPart'] = $ci;
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
        if ($headerIdx === -1) return null;

        $ambil = fn(array $row, ?int $ci) => $ci !== null ? trim((string) ($row[$ci] ?? '')) : '';
        $items = [];
        foreach (array_slice($rows, $headerIdx + 1) as $row) {
            $kodePart = $ambil($row, $col['kodePart']);
            if ($kodePart === '') continue;

            $items[] = [
                'tanggal'     => $col['tanggalFaktur'] !== null ? $this->excelDateToStr($row[$col['tanggalFaktur']] ?? null) : '',
                'kodePart'    => $kodePart,
                'namaPart'    => $ambil($row, $col['namaPart']),
                'qty'         => $this->n($row[$col['qty']] ?? 0),
                'nomorFaktur' => $ambil($row, $col['nomorFaktur']),
                'lokasi'      => $ambil($row, $col['lokasi']),
                'kode'        => $ambil($row, $col['kode']),
                'unitUsaha'   => $ambil($row, $col['unitUsaha']),
            ];
        }

        return $items;
    }

    // Bentuk 2 — "LAPORAN PEMBELIAN (Psch)": header merged-cell, sehingga posisi
    // label ≠ posisi data (pola yang sama dengan parser HGP/Piutang Reguler).
    // Ditemukan lewat kolom "QTY" yang posisinya stabil relatif terhadap kolom lain:
    //   TGL | NAMA SUPPLIER | NO.BUKTI | KODE PART+NAMA BARANG | QTY |
    //   HARGA BELI | DISCOUNT | NETTO | TGL.JTO
    // Offset relatif terhadap kolom QTY: tgl=-9, noBukti=-6, kodePart=-4, namaBarang=-3.
    private function bacaLaporanPembelian(array $rows): ?array
    {
        $colQty = null;
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                if (strtoupper(trim((string) $cell)) === 'QTY') {
                    $colQty = $ci;
                    break 2;
                }
            }
        }
        // Kolom QTY di bentuk ini selalu punya 9 kolom di kirinya. Kalau tidak,
        // yang ketemu itu judul "Qty" milik bentuk lain — bukan laporan Psch.
        if ($colQty === null || $colQty < 9) return null;

        $c = fn(int $offset) => $colQty + $offset;
        $items = [];
        foreach ($rows as $row) {
            $tglRaw = $row[$c(-9)] ?? null;
            // Baris data selalu diawali tanggal (angka serial Excel). Baris
            // header/kosong/tanda-tangan di footer tidak numerik → dilewati.
            if (!is_numeric($tglRaw)) continue;

            $kodePart = trim((string) ($row[$c(-4)] ?? ''));
            $namaBarang = trim((string) ($row[$c(-3)] ?? ''));
            if ($kodePart === '' && $namaBarang === '') continue;

            $items[] = [
                'tanggal'     => $this->excelDateToStr($tglRaw),
                'kodePart'    => $kodePart,
                'namaPart'    => $namaBarang,
                'qty'         => $this->n($row[$colQty] ?? 0),
                'nomorFaktur' => trim((string) ($row[$c(-6)] ?? '')),
                'lokasi'      => '',
                'kode'        => '',
                'unitUsaha'   => '',
            ];
        }

        return $items;
    }

    /**
     * Laporan pembelian yang diekspor TANPA baris header — misalnya:
     *
     *   6 | M408/HGP/VIII/26/-- | 46265 | ALGUPP00 | PT. CAPELLA ... | 06455KVBT01 | PAD SET FR | 10
     *
     * Kolomnya dikenali dari ISI, bukan dari judul yang memang tidak ada:
     * - Kode Part  : kolom teks tanpa spasi dengan nilai paling beragam
     *                (kode supplier ikut berbentuk begitu tapi nilainya seragam)
     * - Nama Barang: kolom di sebelah kanannya, berisi teks bebas (umumnya berspasi)
     * - QTY        : kolom angka pertama di kanan Nama Barang
     * - No. Faktur : kolom dengan paling banyak nilai bergaris miring ("M408/HGP/...")
     * - Tanggal    : kolom angka di kiri Kode Part yang nilainya masuk akal sebagai
     *                nomor seri tanggal Excel (opsional — sebagian ekspor tidak punya)
     *
     * Sengaja tidak memakai posisi kolom tetap: satu berkas contoh bukan jaminan
     * semua cabang mengekspor dengan lebar kolom yang sama persis.
     */
    private function bacaTanpaHeader(array $rows, string $label): array
    {
        $stat = [];   // kolom => [isi, angka, teksTanpaSpasi, berspasi, garisMiring, nilai unik]
        foreach ($rows as $row) {
            foreach ($row as $ci => $cell) {
                $v = trim((string) $cell);
                if ($v === '') continue;
                $stat[$ci] ??= ['isi' => 0, 'angka' => 0, 'kode' => 0, 'spasi' => 0, 'miring' => 0, 'unik' => []];
                $stat[$ci]['isi']++;
                if (is_numeric($v)) $stat[$ci]['angka']++;
                if (!is_numeric($v) && !str_contains($v, ' ')) $stat[$ci]['kode']++;
                if (str_contains($v, ' ')) $stat[$ci]['spasi']++;
                if (str_contains($v, '/')) $stat[$ci]['miring']++;
                $stat[$ci]['unik'][$v] = true;
            }
        }
        if (!$stat) {
            abort(422, "File {$label} kosong — tidak ada baris data yang bisa dibaca.");
        }
        ksort($stat);
        $unik = fn(int $ci) => count($stat[$ci]['unik']);
        $porsi = fn(int $ci, string $k) => $stat[$ci]['isi'] > 0 ? $stat[$ci][$k] / $stat[$ci]['isi'] : 0.0;

        // Dicari SATU rangkaian tiga kolom berdampingan: kode part → nama barang →
        // qty. Menebak per kolom sendiri-sendiri tidak cukup — nomor faktur pun
        // berbentuk kode tanpa spasi dan sama beragamnya dengan kode part, jadi
        // yang membedakan justru tetangganya: hanya kode part yang diikuti kolom
        // nama barang (teks bebas) lalu kolom angka.
        $colPart = $colNama = $colQty = null;
        foreach (array_keys($stat) as $ci) {
            if ($porsi($ci, 'kode') < 0.6 || $unik($ci) < 3) continue;   // calon kode part
            $nama = $ci + 1;
            if (!isset($stat[$nama]) || $porsi($nama, 'angka') > 0.3) continue;
            // Nama barang: teks bebas — umumnya berspasi ("PAD SET FR"), atau
            // setidaknya cukup panjang untuk sebuah deskripsi.
            $panjangRata = 0;
            foreach (array_keys($stat[$nama]['unik']) as $v) $panjangRata += mb_strlen((string) $v);
            $panjangRata = $unik($nama) > 0 ? $panjangRata / $unik($nama) : 0;
            if ($porsi($nama, 'spasi') < 0.3 && $panjangRata < 8) continue;

            $qty = null;
            foreach (array_keys($stat) as $cj) {
                if ($cj <= $nama) continue;
                if ($porsi($cj, 'angka') >= 0.95) { $qty = $cj; break; }
            }
            if ($qty === null) continue;

            if ($colPart === null || $unik($ci) > $unik($colPart)) {
                [$colPart, $colNama, $colQty] = [$ci, $nama, $qty];
            }
        }
        if ($colPart === null || $colQty === null) {
            abort(422, "Format file {$label} tidak dikenali — kolom Kode Part & QTY tidak ditemukan, "
                . 'baik lewat judul kolom maupun lewat isinya. Pastikan berkas yang diunggah memang '
                . 'laporan pembelian.');
        }

        // No. Faktur: paling khas berbentuk "M408/HGP/VIII/26/--", jadi kolom yang
        // isinya bergaris miring didahulukan. Kalau cabangnya memakai penomoran
        // tanpa garis miring, jatuh ke kolom kode lain yang paling beragam —
        // biasanya memang nomor bukti.
        $colFaktur = null;
        foreach ([true, false] as $harusMiring) {
            foreach (array_keys($stat) as $ci) {
                if ($ci === $colPart || $ci === $colNama || $ci === $colQty) continue;
                if ($harusMiring ? $porsi($ci, 'miring') < 0.6 : $porsi($ci, 'kode') < 0.6) continue;
                if ($unik($ci) < 2) continue;   // kolom konstan (kode supplier) bukan nomor bukti
                if ($colFaktur === null || $unik($ci) > $unik($colFaktur)) $colFaktur = $ci;
            }
            if ($colFaktur !== null) break;
        }
        // Tanggal: angka di kiri Kode Part yang masuk akal sebagai seri tanggal Excel
        // (sekitar tahun 2000 ke atas). Nilai seperti "6" atau qty tidak lolos.
        $colTgl = null;
        foreach (array_keys($stat) as $ci) {
            if ($ci >= $colPart || $porsi($ci, 'angka') < 0.95) continue;
            $masukAkal = true;
            foreach (array_keys($stat[$ci]['unik']) as $v) {
                if (!is_numeric($v) || (float) $v < 36526 || (float) $v > 80000) { $masukAkal = false; break; }
            }
            if ($masukAkal) { $colTgl = $ci; break; }
        }

        $items = [];
        foreach ($rows as $row) {
            $kodePart   = trim((string) ($row[$colPart] ?? ''));
            $namaBarang = $colNama !== null ? trim((string) ($row[$colNama] ?? '')) : '';
            if ($kodePart === '' && $namaBarang === '') continue;
            // Baris data selalu punya qty berupa angka; baris judul/total/tanda
            // tangan di ekor berkas tidak, jadi ikut tersaring di sini.
            $qtyRaw = $row[$colQty] ?? null;
            if (!is_numeric($qtyRaw)) continue;

            $items[] = [
                'tanggal'     => $colTgl !== null ? $this->excelDateToStr($row[$colTgl] ?? null) : '',
                'kodePart'    => $kodePart,
                'namaPart'    => $namaBarang,
                'qty'         => $this->n($qtyRaw),
                'nomorFaktur' => $colFaktur !== null ? trim((string) ($row[$colFaktur] ?? '')) : '',
                'lokasi'      => '',
                'kode'        => '',
                'unitUsaha'   => '',
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
