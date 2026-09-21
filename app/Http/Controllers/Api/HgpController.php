<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MengunciDataPemeriksaan;
use App\Http\Controllers\Concerns\PenyegaranRingkas;
use App\Http\Controllers\Concerns\MenjagaHasilPemeriksaan;
use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbAhmOil;
use App\Models\DbHet;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HgpController extends Controller
{
    use RequiresAuditorAuditee;
    use MenjagaHasilPemeriksaan;
    use MengunciDataPemeriksaan;
    use PenyegaranRingkas;

    // Dua jenis audit memuat hanya sebagian isi berkas onhand untuk diperiksa,
    // dan aturannya BERBEDA:
    //
    //   Audit Online Kas + HGP & AHM Oils : 30 item acak saja.
    //   Audit Kas + HGP & AHM Oils        : SELURUH item yang ada di database
    //                                       AHM Oils, ditambah 30 part lain acak.
    //
    // Jenis audit lain (Audit Full SO, Audit Warehouse PART, dst) tidak
    // terpengaruh -- tetap menampilkan seluruh item. Tool terpisah "RSA HGP &
    // AHM Oils" juga punya aturannya sendiri dan tidak ikut ke sini.
    private const JENIS_SAMPLE_ACAK = 'Audit Online Kas + HGP & AHM Oils';
    private const JENIS_SAMPLE_OLI  = 'Audit Kas + HGP & AHM Oils';
    private const SAMPLE_SIZE = 30;

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->jawabanPemeriksaan(PemeriksaanHgp::class, $request));
    }

    // Simpan-penuh: menulis ULANG seluruh items_json dari apa yang dikirim browser.
    // Karena itu payload-nya diperiksa dulu terhadap yang sudah tersimpan — array
    // lama dari satu perangkat tidak boleh menghapus hasil scan perangkat lain yang
    // lebih baru (lihat MenjagaHasilPemeriksaan). Mode:
    //   merge (bawaan) — ditolak 409 kalau ada jejak pemeriksaan yang akan hilang
    //   import         — daftar & saldo baru dari Excel, hasil scan di server dibawa
    //   replace        — benar-benar menimpa; hanya dari "Hapus Semua Data"
    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'hgp');
        $who    = $request->user()?->username ?? $request->user()?->email;
        $mode   = (string) $request->input('mode', 'merge');
        $items  = (array) $request->input('items', []);

        return $this->denganKunciPemeriksaan(PemeriksaanHgp::class, $planId,
            function (?PemeriksaanHgp $rec) use ($planId, $who, $mode, $items) {
                $tersimpan = $rec?->items_json ?? [];

                if ($mode === 'import') {
                    $items = $this->bawaHasilPemeriksaan($items, $tersimpan);
                } elseif ($mode !== 'replace') {
                    $hilang = $this->pemeriksaanYangHilang($items, $tersimpan);
                    if ($hilang !== []) {
                        return response()->json([
                            'message' => 'Data di server sudah lebih baru dari yang ada di layar ini — '
                                . count($hilang) . ' item yang sudah diperiksa akan hilang kalau ditimpa. '
                                . 'Muat ulang tab HGP dulu, hasil scan Anda yang belum terkirim tetap aman.',
                            'stale'   => true,
                            'noPart'  => array_slice($hilang, 0, 20),
                        ], 409);
                    }
                }

                $rec = PemeriksaanHgp::updateOrCreate(
                    ['plan_audit_id' => $planId],
                    ['items_json' => $items, 'updated_by' => $who]
                );
                if (!$rec->created_by) $rec->update(['created_by' => $who]);

                return response()->json(['message' => 'Data HGP tersimpan.', 'data' => $this->dataDenganSidik($rec->fresh())]);
            });
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

        return $this->denganKunciPemeriksaan(PemeriksaanHgp::class, $planId,
            function (?PemeriksaanHgp $rec) use ($request, $planId, $noPart, $qty, $who) {
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
            });
    }

    /**
     * Ganti judul kolom yang menambah hitungan fisik (bawaannya "WO").
     *
     * Yang dihitung tidak berubah sama sekali -- hanya namanya, sebab tiap
     * cabang menyebutnya berbeda (titipan, display, retur, dan seterusnya).
     * Dibuat endpoint sendiri, bukan lewat simpan-penuh, supaya mengganti
     * nama tidak perlu mengirim ulang seluruh daftar item.
     */
    public function gantiLabelWo(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:20'],
        ]);

        abort_unless($planId, 422, 'plan_audit_id wajib diisi.');

        $label = trim((string) ($data['label'] ?? ''));
        $who   = $request->user()?->username ?? $request->user()?->email;

        // Kolomnya baru ada lewat migration terbaru. Kalau hosting belum
        // menjalankannya, katakan apa adanya -- jangan jatuh jadi "Server Error".
        abort_unless(
            \Illuminate\Support\Facades\Schema::hasColumn('pemeriksaan_hgp', 'label_wo'),
            422,
            'Struktur database belum diperbarui untuk mengganti judul kolom ini. '
                . 'Jalankan pembaruan struktur database (/deploy/migrate) lebih dulu.'
        );

        return $this->denganKunciPemeriksaan(PemeriksaanHgp::class, $planId,
            function (?PemeriksaanHgp $rec) use ($planId, $label, $who) {
                $rec = $rec ?? new PemeriksaanHgp(['plan_audit_id' => $planId]);

                // Dikosongkan = kembali ke judul bawaan.
                $rec->label_wo   = $label !== '' ? $label : null;
                $rec->updated_by = $who;
                $rec->save();

                return response()->json([
                    'message' => 'Judul kolom disimpan.',
                    'labelWo' => $rec->label_wo ?: PemeriksaanHgp::LABEL_WO_BAWAAN,
                ]);
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

        return $this->denganKunciPemeriksaan(PemeriksaanHgp::class, $planId,
            function (?PemeriksaanHgp $rec) use ($planId, $noPart, $nama, $who) {
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

        // Deteksi header: cari baris yang mengandung kolom "AWAL" (saldo awal)
        // Format onhand: header row punya merged cells, sehingga posisi data digeser -1 dari label
        // Header: col[2]="NO PART", col[4]="NAMA PART", col[5]="AWAL", col[10]="KETERANGAN"
        // Data:   col[1]=noPart,    col[2]=namapart,    col[5]=awal,   col[10]=ket
        // Rumus: colNoPart_data = colAwal - 4, colNama_data = colAwal - 3, colKet_data = colAwal + 5
        // Laporan stok WHS punya judul kolom LENGKAP di tiap kolomnya (NO, NO
        // PART, NAMA PART, AWAL, MASUK, ... AKHIR, Fisik, Selisih) tanpa satu
        // pun kolom gabungan, jadi kolomnya bisa dibaca dari namanya sendiri.
        // Kalau bentuknya bukan itu, dipakai aturan lama berbasis jarak dari
        // kolom AWAL -- lihat catatan di bacaLewatJudul().
        if ($lewatJudul = $this->bacaLewatJudul($rows)) {
            return $this->jawabanImpor($lewatJudul, $request);
        }

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

        return $this->jawabanImpor($items, $request);
    }

    // Judul kolom laporan stok, apa adanya seperti tertulis di berkas WHS.
    // Kuncinya yang dipakai di seluruh aplikasi; nilainya daftar tulisan yang
    // dianggap sama.
    private const JUDUL_KOLOM = [
        'noPart'           => ['no part', 'no_part', 'part number', 'part no', 'kode part', 'kode'],
        'nama'             => ['nama part', 'nama_part', 'nama barang', 'sparepart', 'nama'],
        'awal'             => ['awal', 'saldo awal', 'stok awal'],
        'masuk'            => ['masuk'],
        'keluar'           => ['keluar'],
        'adj'              => ['adj', 'adjust', 'adjustment'],
        'mm1'              => ['mm1'],
        'mk1'              => ['mk1'],
        'mm2'              => ['mm2'],
        'mk2'              => ['mk2'],
        'fakturBelumKutip' => ['faktur belum kutip', 'fkt belum kutip'],
        'claim'            => ['claim', 'klaim'],
        'akhir'            => ['akhir', 'saldo akhir', 'stok akhir'],
        'keterangan'       => ['keterangan', 'ket', 'lokasi'],
    ];

    // Kolom laporan stok yang dibawa apa adanya ke layar, di luar yang sudah
    // punya tempat sendiri (noPart, nama, akhir -> saldoAkhir).
    //
    // Fisik dan Selisih sengaja TIDAK ikut: keduanya hasil pemeriksaan di
    // aplikasi ini (fisik dari scan, selisih dihitung dari saldo akhir), bukan
    // isi laporan stok. Berkas WHS yang benar pun tidak memuatnya.
    private const KOLOM_STOK = [
        'awal', 'masuk', 'keluar', 'adj', 'mm1', 'mk1', 'mm2', 'mk2',
        'fakturBelumKutip', 'claim',
    ];

    /**
     * Baca berkas yang baris judulnya RAPAT -- tiap kolom punya judul sendiri,
     * tidak ada kolom gabungan (merged). Laporan stok WHS bentuknya begitu, dan
     * susunan kolomnya sama sekali berbeda dari berkas onhand cabang: AWAL ada
     * di kolom ke-4, sementara aturan lama mengira nomor part berjarak empat
     * kolom di KIRI AWAL -- pada berkas ini jatuh di luar tabel.
     *
     * Berkas onhand cabang sengaja TIDAK lewat sini: judulnya memakai kolom
     * gabungan sehingga posisi judul bergeser dari posisi datanya, dan membaca
     * lewat nama justru menghasilkan kolom yang salah. Yang membedakan keduanya
     * cuma satu hal yang bisa diperiksa: ada tidaknya judul kolom yang kosong
     * di antara judul pertama dan terakhir.
     *
     * @return array<int, array<string, mixed>>|null null kalau bentuknya bukan ini
     */
    private function bacaLewatJudul(array $rows): ?array
    {
        $peta = null;
        $mulai = 0;

        foreach ($rows as $i => $row) {
            if ($i > 30) break;   // judul selalu di awal berkas

            $calon = $this->petaJudulRapat($row);

            if ($calon !== null) {
                $peta  = $calon;
                $mulai = $i + 1;
                break;
            }
        }

        if ($peta === null) {
            return null;
        }

        $items = [];

        foreach (array_slice($rows, $mulai) as $row) {
            $noPart = trim((string) ($row[$peta['noPart']] ?? ''));
            $nama   = isset($peta['nama']) ? trim((string) ($row[$peta['nama']] ?? '')) : '';

            if ($noPart === '' && $nama === '') continue;

            // Baris jumlah/total di kaki tabel, bukan data part.
            if ($noPart === '' || preg_match('/^(total|jumlah|grand)/i', $noPart)) continue;

            $saldoAkhir = isset($peta['akhir']) ? $this->n($row[$peta['akhir']] ?? 0) : 0.0;

            $stok = [];
            foreach (self::KOLOM_STOK as $kolom) {
                if (!isset($peta[$kolom])) continue;

                $isi = $row[$peta[$kolom]] ?? null;

                // Kolom yang memang kosong di berkas dibiarkan kosong, bukan
                // dijadikan 0 -- "belum diisi" dan "nol" bukan hal yang sama.
                if ($isi === null || trim((string) $isi) === '') continue;

                $stok[$kolom] = $this->n($isi);
            }

            $items[] = [
                'noPart'     => $noPart,
                'sparepart'  => $nama !== '' ? $nama : $noPart,
                'saldoAkhir' => $saldoAkhir,
                'fisik'      => 0,
                'akhir'      => $saldoAkhir,
                'selisih'    => -$saldoAkhir,
                'keterangan' => isset($peta['keterangan']) ? trim((string) ($row[$peta['keterangan']] ?? '')) : '',
                'tgl'        => date('Y-m-d'),
                'logScan'    => [],
                'stok'       => $stok,
            ];
        }

        return $items === [] ? null : $items;
    }

    /**
     * Peta kolom dari satu baris judul, HANYA kalau baris itu rapat: setiap
     * kolom di antara judul pertama dan terakhir punya tulisan. Satu saja yang
     * kosong berarti ada kolom gabungan, dan posisi data tidak bisa dipercaya
     * sama dengan posisi judulnya.
     *
     * @return array<string, int>|null
     */
    private function petaJudulRapat(array $row): ?array
    {
        $isi = [];

        foreach ($row as $i => $sel) {
            $teks = strtolower(trim((string) $sel));

            if ($teks !== '') {
                $isi[$i] = $teks;
            }
        }

        if (count($isi) < 4) {
            return null;
        }

        $posisi = array_keys($isi);

        for ($i = min($posisi); $i <= max($posisi); $i++) {
            if (!isset($isi[$i])) {
                return null;   // ada judul kosong: kolom gabungan
            }
        }

        $peta = [];

        foreach (self::JUDUL_KOLOM as $kunci => $tulisan) {
            foreach ($isi as $i => $teks) {
                if (in_array($teks, $tulisan, true) && !isset($peta[$kunci])) {
                    $peta[$kunci] = $i;
                }
            }
        }

        // Tanpa nomor part tidak ada yang bisa diperiksa; tanpa saldo akhir
        // tidak ada pembanding fisiknya.
        return isset($peta['noPart'], $peta['akhir']) ? $peta : null;
    }

    /** Sampling & bentuk jawaban impor, sama untuk kedua cara baca kolom. */
    private function jawabanImpor(array $items, Request $request): JsonResponse
    {
        $totalFound = count($items);
        $planId     = $request->input('planAuditId') ?? $request->input('plan_audit_id');

        if ($aturan = $this->aturanSample($planId)) {
            $denganOli = $aturan === 'oli';

            [$items, $sampled, $jumlahOli] = $this->applySample($items, self::SAMPLE_SIZE, $denganOli);

            return response()->json([
                'data'       => $items,
                'total'      => count($items),
                'totalFound' => $totalFound,
                'sampleSize' => self::SAMPLE_SIZE,
                'sampled'    => $sampled,
                'ahmOil'     => $denganOli ? $jumlahOli : null,
                // Master AHM Oils belum diisi: yang masuk cuma 30 part acak,
                // dan tidak ada satu pun oli yang diperiksa. Auditor harus tahu
                // itu sebelum mulai menghitung, bukan sesudah laporannya jadi.
                'catatan'    => $denganOli && $jumlahOli === 0
                    ? 'Tidak ada satu pun item yang cocok dengan database AHM Oils — '
                        . 'periksa menu Database → AHM Oils, lalu import ulang berkas ini.'
                    : null,
            ]);
        }

        return response()->json(['data' => $items, 'total' => $totalFound]);
    }

    /**
     * Aturan pemuatan item untuk plan ini: null (seluruhnya), 'acak', atau 'oli'.
     */
    private function aturanSample(mixed $planId): ?string
    {
        if (!$planId) {
            return null;
        }

        $jenis = PlanAudit::where('id', $planId)->value('jenis_audit');

        return match ($jenis) {
            self::JENIS_SAMPLE_ACAK => 'acak',
            self::JENIS_SAMPLE_OLI  => 'oli',
            default                 => null,
        };
    }

    // Sama seperti RsaHgpController::applySample() — sample diambil acak tapi
    // dikembalikan dalam urutan asli file (bukan urutan acak) supaya lebih
    // mudah dibaca auditor saat scan. Kalau jumlah item <= sampleSize, tidak
    // perlu disampling, kembalikan semuanya apa adanya.
    /**
     * Ambil item yang akan diperiksa.
     *
     * Pada aturan 'oli', SELURUH item yang ada di database AHM Oils ikut,
     * ditambah $sampleSize part lain yang diambil acak. Pada aturan 'acak',
     * $sampleSize item diambil acak dari seluruh berkas tanpa memandang isinya.
     *
     * Urutannya dikembalikan seperti urutan di berkas aslinya, bukan oli dulu
     * baru sparepart: auditor menyusuri rak mengikuti urutan itu.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool, 2: int}
     *         [item terpilih, ada yang tidak ikut, jumlah item AHM Oils]
     */
    private function applySample(array $items, int $sampleSize, bool $denganOli): array
    {
        $kodeOli = $denganOli ? $this->kodeAhmOil() : [];

        $indeksOli  = [];
        $indeksLain = [];

        foreach ($items as $i => $it) {
            $kode = strtolower(trim((string) ($it['noPart'] ?? '')));

            if ($denganOli && $kode !== '' && isset($kodeOli[$kode])) {
                $indeksOli[] = $i;
            } else {
                $indeksLain[] = $i;
            }
        }

        $terpilihLain = $indeksLain;

        if (count($indeksLain) > $sampleSize) {
            $acak = (array) array_rand($indeksLain, $sampleSize);
            $terpilihLain = array_map(fn($k) => $indeksLain[$k], $acak);
        }

        $indeks = array_merge($indeksOli, $terpilihLain);
        sort($indeks);

        $terpilih = array_map(fn($i) => $items[$i], $indeks);

        return [array_values($terpilih), count($terpilih) < count($items), count($indeksOli)];
    }

    /**
     * Kode AHM Oils dari master, sebagai peta untuk pencocokan cepat.
     *
     * Pencocokannya sama persis dengan yang dipakai rekap selisih (exportSelisih)
     * dan Report Audit PDF, supaya ketiga tempat mengelompokkan item yang sama.
     *
     * @return array<string, mixed>
     */
    private function kodeAhmOil(): array
    {
        return DbAhmOil::query()->pluck('kode')
            ->map(fn($k) => strtolower(trim((string) $k)))
            ->filter()
            ->flip()
            ->all();
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

    // Export item yang selisih-nya tidak nol saja ke Excel, supaya auditor tidak
    // perlu menyaring manual dari ribuan baris di layar. Selisih dihitung ulang
    // di sini dari field mentahnya (bukan dipercaya dari items_json apa adanya)
    // supaya hasilnya tetap benar walau field turunan itu sempat tidak sinkron.
    // Dipecah ke 2 sheet (AHM OIL'S & SPAREPART) memakai pencocokan kode yang
    // SAMA dengan rekap selisih di Report Audit PDF (ReportPdfController::
    // splitOilSparepart) — supaya kedua tempat konsisten mengelompokkan item
    // yang sama, dan auditor tidak perlu memisah manual lagi.
    public function exportSelisih(Request $request): StreamedResponse
    {
        $planId = $request->query('plan_audit_id');
        abort_unless($planId, 422, 'plan_audit_id wajib diisi.');

        $plan      = PlanAudit::find($planId);
        $rec       = PemeriksaanHgp::where('plan_audit_id', $planId)->first();
        $auditor   = PemeriksaanAuditor::where('plan_audit_id', $planId)->where('tool', 'hgp')->first();
        $items     = $rec?->items_json ?? [];

        $kodeOli = DbAhmOil::query()->pluck('kode')
            ->map(fn($k) => strtolower(trim((string) $k)))
            ->filter()
            ->flip();

        $oilBaris = [];
        $sparepartBaris = [];
        foreach ($items as $it) {
            $fisik  = $this->n($it['fisik'] ?? 0);
            $wo     = $this->n($it['wo'] ?? 0);
            $saldo  = $this->n($it['saldoAkhir'] ?? ($it['saldoAwal'] ?? 0));
            $akhir  = $saldo - ($fisik + $wo);
            $selisih = ($fisik + $wo) - $saldo;
            if ($selisih === 0.0) continue;

            $harga  = $this->n($it['hargaHet'] ?? 0);
            $baris = [
                'noPart'     => $it['noPart'] ?? '',
                'sparepart'  => $it['sparepart'] ?? '',
                'tgl'        => $it['tgl'] ?? '',
                'saldo'      => $saldo,
                'fisik'      => $fisik,
                'wo'         => $wo,
                'akhir'      => $akhir,
                'selisih'    => $selisih,
                'harga'      => $harga,
                'jumlah'     => $harga * $selisih,
                'keterangan' => $it['keterangan'] ?? '',
            ];

            $kode = strtolower(trim((string) ($it['noPart'] ?? '')));
            if ($kode !== '' && $kodeOli->has($kode)) {
                $oilBaris[] = $baris;
            } else {
                $sparepartBaris[] = $baris;
            }
        }

        $infoLines = [
            'No SPT: ' . ($plan->no_spt ?? '-'),
            'Cabang/Area: ' . ($plan->cabang_area ?? $plan->cabang ?? '-'),
            'Auditor: ' . ($auditor->nama_auditor ?? '-') . '   Auditee: ' . ($auditor->nama_auditee ?? '-'),
        ];

        $spreadsheet = new Spreadsheet();
        $labelWo = $rec?->label_wo ?: PemeriksaanHgp::LABEL_WO_BAWAAN;

        $this->tulisSheetSelisih($spreadsheet->getActiveSheet(), "AHM OIL'S", $infoLines, $oilBaris, $labelWo);
        $sheetSparepart = $spreadsheet->createSheet();
        $this->tulisSheetSelisih($sheetSparepart, 'SPAREPART', $infoLines, $sparepartBaris, $labelWo);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'hgp-selisih-' . ($plan->no_spt ?? $planId) . '-' . now()->format('Y-m-d_H-i') . '.xlsx';
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function tulisSheetSelisih(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $judul,
        array $infoLines,
        array $baris,
        string $labelWo = PemeriksaanHgp::LABEL_WO_BAWAAN
    ): void {
        // Nama sheet Excel tidak boleh mengandung karakter ' \ / ? * [ ] dan
        // maksimal 31 karakter — "AHM OIL'S" punya tanda kutip, jadi dibersihkan.
        $sheet->setTitle(substr(str_replace(["'", '\\', '/', '?', '*', '[', ']'], '', $judul), 0, 31));

        foreach ($infoLines as $i => $line) {
            $sheet->setCellValue([1, $i + 1], $line);
        }
        $infoRange = 'A1:A' . count($infoLines);
        $sheet->getStyle($infoRange)->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $judulRow = count($infoLines) + 2;
        $sheet->setCellValue([1, $judulRow], $judul . ' (' . count($baris) . ' item selisih)');
        $sheet->getStyle('A' . $judulRow)->getFont()->setBold(true)->setSize(12);

        $headers = ['No', 'No. Part', 'Nama Sparepart', 'Tanggal', 'Saldo', 'Fisik', $labelWo, 'Akhir', 'Selisih', 'Harga HET', 'Jumlah', 'Keterangan'];
        $headerRow = $judulRow + 1;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, $headerRow], $header);
        }
        $lastCol = count($headers);
        $headerRange = 'A' . $headerRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIndex = $headerRow + 1;
        foreach ($baris as $i => $b) {
            $values = [
                $i + 1, $b['noPart'], $b['sparepart'], $b['tgl'],
                $b['saldo'], $b['fisik'], $b['wo'], $b['akhir'], $b['selisih'],
                $b['harga'], $b['jumlah'], $b['keterangan'],
            ];
            foreach ($values as $ci => $value) {
                $sheet->setCellValue([$ci + 1, $rowIndex], $value);
            }
            $rowIndex++;
        }

        if ($rowIndex > $headerRow + 1) {
            $dataRange = 'A' . $headerRow . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . ($rowIndex - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        foreach (range(1, $lastCol) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }
    }
}
