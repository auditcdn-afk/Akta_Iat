<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbAhmOil;
use App\Models\DbGrading;
use App\Models\DbHargaSmh;
use App\Models\DbHet;
use App\Models\DbMt;
use App\Models\DbPerlengkapan;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    // Baris ditulis ke database tiap kali tampungan sebanyak ini terkumpul,
    // bukan ditumpuk semuanya sampai akhir. Pada berkas HET nyata (63.049
    // baris) menumpuk semuanya menahan ~18 MB tambahan di memori tanpa guna —
    // sementara hasilnya sama saja, karena seluruh penulisan tetap berada di
    // dalam satu transaksi.
    private const UKURAN_TAMPUNGAN = 2000;

    /** Hasil pemeriksaan indeks unik, agar tidak ditanyakan ulang ke database
     *  tiap potongan -- satu import berkas besar memanggilnya puluhan kali. */
    private array $cacheIndeksUnik = [];

    private static array $typeMap = [
        'harga-smh'    => DbHargaSmh::class,
        'plafon'       => DbPlafon::class,
        'perlengkapan' => DbPerlengkapan::class,
        'unit-usaha'   => DbUnitUsaha::class,
        'grading'      => DbGrading::class,
        'mt'           => DbMt::class,
        'het'          => DbHet::class,
        'ahm-oil'      => DbAhmOil::class,
    ];

    private static array $colMap = [
        'harga-smh'    => ['kode_model', 'nama_smh', 'harga'],
        'plafon'       => ['kode', 'nama', 'nilai', 'keterangan'],
        'perlengkapan' => ['kode', 'wilayah', 'nama', 'satuan', 'qty', 'keterangan'],
        'unit-usaha'   => ['unit_usaha', 'wilayah', 'jenis'],
        'grading'      => ['id_grading', 'jenis', 'wilayah', 'nama_pemeriksaan', 'hasil_pemeriksaan', 'nilai', 'bknf', 'pknf', 'bkf', 'pkf', 'bnknf', 'pnknf', 'bnkf', 'pnkf'],
        // Harga TIDAK dimasukkan di sini secara posisi tetap — lihat detectMtColumns()
        // di import(). Berkas nyata yang diunggah auditor membuktikan kenapa:
        // posisi kolom Harga berbeda-beda antar template (kadang persis setelah
        // Kode Peralatan, kadang ada kolom "Gambar" di antaranya), jadi mengunci
        // ke satu indeks tetap gampang meleset lagi begitu templatenya berubah
        // sedikit saja — sama seperti yang baru saja terjadi.
        'mt'           => ['nomor', 'nama_singkat', '_x', 'nama_peralatan', 'kode_peralatan'],
        'het'          => ['kode', 'nama', 'harga_het'],
        'ahm-oil'      => ['kode', 'nama', 'keterangan'],
    ];

    private static array $searchCols = [
        'harga-smh'    => ['kode_model', 'nama_smh'],
        'plafon'       => ['kode', 'nama', 'keterangan'],
        'perlengkapan' => ['kode', 'wilayah', 'nama', 'keterangan'],
        'unit-usaha'   => ['unit_usaha', 'wilayah', 'jenis'],
        'grading'      => ['id_grading', 'jenis', 'wilayah', 'nama_pemeriksaan', 'hasil_pemeriksaan'],
        'mt'           => ['nama_singkat', 'nama_peralatan', 'kode_peralatan', 'jenis'],
        'het'          => ['kode', 'nama'],
        'ahm-oil'      => ['kode', 'nama'],
    ];

    // Unique key(s) per type — used for upsert during import
    private static array $uniqueKeys = [
        'harga-smh'    => ['kode_model'],
        'plafon'       => ['kode'],
        'perlengkapan' => ['kode', 'wilayah'],
        'unit-usaha'   => ['unit_usaha', 'wilayah'],
        'grading'      => ['id_grading'],
        'mt'           => ['kode_peralatan', 'jenis'],
        'het'          => ['kode'],
        'ahm-oil'      => ['kode'],
    ];

    private function resolveModel(string $type): string
    {
        $class = self::$typeMap[$type] ?? null;
        if (!$class) {
            abort(404, "Tipe database '{$type}' tidak ditemukan.");
        }
        return $class;
    }

    /**
     * Daftar lengkap unit usaha (tanpa paginasi) untuk dropdown.
     * Dipakai di form Pengguna agar wilayah terisi otomatis saat unit usaha dipilih.
     */
    public function unitUsahaOptions(): JsonResponse
    {
        $rows = DbUnitUsaha::query()
            ->orderBy('unit_usaha')
            ->get(['id', 'unit_usaha', 'wilayah', 'jenis']);

        return response()->json([
            'ok'   => true,
            'data' => $rows->map(fn(DbUnitUsaha $r) => [
                'id'        => $r->id,
                'unitUsaha' => $r->unit_usaha,
                'wilayah'   => $r->wilayah,
                'jenis'     => $r->jenis,
            ]),
        ]);
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $model = $this->resolveModel($type);
        $q = trim((string) $request->query('q', ''));
        $searchCols = self::$searchCols[$type] ?? [];

        $query = $model::query()->orderBy('id');

        if ($q !== '' && !empty($searchCols)) {
            $query->where(function ($sub) use ($q, $searchCols) {
                foreach ($searchCols as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        $page = (int) $request->query('page', 1);
        $perPage = 100;
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'ok'          => true,
            'data'        => collect($paginated->items())->map(fn(Model $m) => $m->toAktaArray()),
            'total'       => $paginated->total(),
            'perPage'     => $paginated->perPage(),
            'currentPage' => $paginated->currentPage(),
            'lastPage'    => $paginated->lastPage(),
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->resolveModel($type);
        $cols  = self::$colMap[$type] ?? [];

        $record = $model::create($request->only($cols));

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil ditambahkan.',
            'data'    => $record->toAktaArray(),
        ], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model  = $this->resolveModel($type);
        $cols   = self::$colMap[$type] ?? [];
        $record = $model::findOrFail($id);
        $record->update($request->only($cols));

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil diperbarui.',
            'data'    => $record->fresh()->toAktaArray(),
        ]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type);
        $model::findOrFail($id)->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil dihapus.',
        ]);
    }

    public function truncate(Request $request, string $type, ActivityLogger $logger): JsonResponse
    {
        $model = $this->resolveModel($type);
        $model::truncate();

        $logger->write($request, 'DB_TRUNCATE', $type, "Hapus semua data database: {$type}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Semua data berhasil dihapus.',
        ]);
    }

    public function import(Request $request, string $type, ActivityLogger $logger): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        // Membaca berkasnya saja sudah makan waktu sendiri di berkas besar
        // (berkas HET nyata berisi 63.049 baris perlu ~2,6 detik hanya untuk
        // diurai), sementara batas bawaan PHP di hosting cuma 30 detik. Import
        // manual begini endpoint yang sesekali dipakai, bukan request biasa,
        // jadi batasnya dilonggarkan. Dibungkus @ karena sebagian hosting
        // mengunci fungsi ini -- kalau ditolak, prosesnya tetap jalan dengan
        // batas bawaan, tidak sampai menggagalkan import.
        @set_time_limit(300);

        $file      = $request->file('file');
        $ext       = strtolower($file->getClientOriginalExtension());
        $model     = $this->resolveModel($type);
        $cols      = self::$colMap[$type] ?? [];

        $rows = match ($ext) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx'       => $this->parseXlsx($file->getRealPath()),
            default      => throw new \InvalidArgumentException("Format file '{$ext}' tidak didukung. Gunakan .xlsx atau .csv."),
        };

        // Kolom Harga di berkas MT terbukti berpindah-pindah posisi antar
        // template (kadang tepat setelah Kode Peralatan, kadang ada kolom
        // "Gambar" di antaranya) — jadi sebelum baris header dibuang, posisi
        // kolomnya dibaca dulu dari ISI header itu (bukan diasumsikan tetap).
        // Header-nya sendiri juga tidak selalu di baris pertama — berkas MT
        // Lama nyata punya baris kosong pemisah SEBELUM baris header, jadi
        // dicari dulu baris header-nya (melewati baris kosong di atasnya)
        // alih-alih langsung mengasumsikan baris ke-0.
        $mtKolom = null;
        if ($type === 'mt' && !empty($rows)) {
            $headerIdx = $this->mtCariIndeksHeader($rows);
            if ($headerIdx !== null) {
                $mtKolom = $this->detectMtColumns($rows[$headerIdx]);
                array_splice($rows, 0, $headerIdx + 1);
            } else {
                // Tidak ada baris header yang dikenali sampai baris data
                // pertama — berkasnya memang tanpa header sama sekali.
                $mtKolom = $this->detectMtColumns([]);
            }
        }

        // Remove header row (non-numeric first cell)
        if (!empty($rows)) {
            $first = trim((string) ($rows[0][0] ?? ''));
            if ($first !== '' && !is_numeric($first)) {
                array_shift($rows);
            }
        }

        // Flatten multi-group horizontal layout:
        // If the file has more columns than expected, check if it's a uniform repeat
        // (e.g. 10 cols / 5 colCount = 2 groups). Otherwise just take first colCount cols.
        $colCount  = count($cols);
        $flatRows  = [];
        $sampleLen = !empty($rows) ? max(array_map('count', array_slice($rows, 0, 5))) : 0;
        $groups    = 1;

        // Perlengkapan (lebar kolom variabel per baris) dan MT (lebar kolomnya
        // sendiri sudah ditangani lewat $mtKolom di atas, memotongnya ke
        // $colCount di sini akan membuang kolom Harga yang posisinya bisa lebih
        // jauh dari $colCount) — dua-duanya dilewatkan apa adanya, tidak dipotong.
        if ($type !== 'perlengkapan' && $type !== 'mt' && $sampleLen > $colCount && $sampleLen % $colCount === 0) {
            $groups = intdiv($sampleLen, $colCount);
        }

        // Sengaja for + $rows[$i] = null, bukan foreach: tiap baris sumber
        // dibebaskan begitu selesai disalin, sehingga $rows menyusut seiring
        // $flatRows tumbuh alih-alih dua-duanya penuh berbarengan. (foreach
        // tidak bisa dipakai untuk ini — menyentuh $rows di dalamnya justru
        // membuat PHP menggandakan seluruh array.)
        for ($i = 0, $n = count($rows); $i < $n; $i++) {
            $row = $rows[$i];
            $rows[$i] = null;

            if ($type === 'mt') {
                // Lebar barisnya bisa lebih panjang dari $colCount (mis. ada
                // kolom Gambar/Harga) — dibiarkan utuh, disaring di bawah.
                $trimmed = array_map('trim', $row);
                if (!empty(array_filter($trimmed))) {
                    $flatRows[] = $row;
                }
                continue;
            }
            for ($g = 0; $g < $groups; $g++) {
                $slice = array_slice($row, $g * $colCount, $colCount);
                $trimmed = array_map('trim', $slice);
                // Skip slice if first two cells are both empty (likely padding/separator group)
                if (($trimmed[0] ?? '') === '' && ($trimmed[1] ?? '') === '') {
                    continue;
                }
                if (!empty(array_filter($trimmed))) {
                    $flatRows[] = $slice;
                }
            }
        }

        // Dibebaskan di sini, bukan nanti: mulai baris berikutnya $rows tidak
        // dipakai lagi, sementara $flatRows dan kumpulan baris di bawah sama-sama
        // memegang seluruh isi berkas. Tanpa ini ada tiga salinan data hidup
        // berbarengan — cukup untuk melewati batas memori hosting pada berkas besar.
        unset($rows);

        // Baris tidak lagi ditulis satu per satu di dalam perulangan ini --
        // disusun dulu jadi satu kumpulan, baru ditulis massal per potongan
        // di simpanMassal(). Dulu tiap baris memanggil updateOrCreate sendiri
        // (dua query: cek dulu, baru simpan), dan pada berkas HET nyata berisi
        // 63.049 baris itu berarti ~126.000 query dan 2,5 menit. PHP keburu
        // memutusnya di batas 30 detik, dan karena semuanya dibungkus satu
        // transaksi, SELURUH import ikut dibatalkan: gagal total tanpa satu
        // baris pun masuk. Berkas yang sama kini selesai di bawah satu detik.
        $imported  = 0;
        $mtJenis   = ($type === 'mt') ? trim((string) $request->input('mt_jenis', '')) : null;

        DB::transaction(function () use ($flatRows, $model, $cols, $type, $mtJenis, $mtKolom, &$imported) {
            $baris   = [];
            $tampung = function (array $data) use (&$baris, $model, $type) {
                $baris[] = $data;
                if (count($baris) >= self::UKURAN_TAMPUNGAN) {
                    $this->simpanMassal($model, $type, $baris);
                    $baris = [];
                }
            };

            foreach ($flatRows as $row) {
                if (empty(array_filter(array_map('trim', $row)))) {
                    continue;
                }

                // MT: kolomnya diambil lewat posisi yang terdeteksi dari header
                // (atau posisi lama sebagai fallback kalau berkasnya tanpa
                // header) — bukan potongan tetap $colCount, lihat catatan di
                // atas soal kenapa Harga bisa berpindah posisi.
                if ($type === 'mt') {
                    $ambil = fn(?int $ci) => $ci !== null ? trim((string) ($row[$ci] ?? '')) : '';
                    $nomor = $ambil($mtKolom['nomor']);
                    $nama_singkat = $ambil($mtKolom['nama_singkat']);
                    $nama_peralatan = $ambil($mtKolom['nama_peralatan']);
                    $kode_peralatan = $ambil($mtKolom['kode_peralatan']);
                    $hargaRaw = $ambil($mtKolom['harga']);

                    if ($nama_singkat === '' && $nama_peralatan === '' && $kode_peralatan === '') continue;

                    $harga = null;
                    if ($hargaRaw !== '') {
                        $numHarga = preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $hargaRaw));
                        $harga = is_numeric($numHarga) ? $numHarga : null;
                    }

                    $data = [
                        'nomor'          => $nomor !== '' ? $nomor : null,
                        'nama_singkat'   => $nama_singkat !== '' ? $nama_singkat : null,
                        'nama_peralatan' => $nama_peralatan !== '' ? $nama_peralatan : null,
                        'kode_peralatan' => $kode_peralatan !== '' ? $kode_peralatan : null,
                        'harga'          => $harga,
                    ];
                    if ($mtJenis !== null && $mtJenis !== '') {
                        $data['jenis'] = $mtJenis;
                    }
                    $tampung($data);
                    $imported++;
                    continue;
                }

                // Special handling for perlengkapan: XLS format is
                // TIPE(col0) | NOSIN(col1) | ACEH(col2) | RIAU(col3) | KEPRI(col4) | Type(col5)
                // Each region column contains comma-separated items in a single cell.
                if ($type === 'perlengkapan') {
                    $tipe  = trim((string) ($row[0] ?? ''));
                    $nosin = strtoupper(trim((string) ($row[1] ?? '')));
                    if ($nosin === '') continue;

                    $regionMap = [
                        'aceh'  => trim((string) ($row[2] ?? '')),
                        'riau'  => trim((string) ($row[3] ?? '')),
                        'kepri' => trim((string) ($row[4] ?? '')),
                    ];

                    $hasRegions = array_filter($regionMap, fn($v) => $v !== '');

                    if ($hasRegions) {
                        foreach ($regionMap as $wilayah => $keterangan) {
                            if ($keterangan === '') continue;
                            $tampung([
                                'kode' => $nosin, 'wilayah' => $wilayah,
                                'nama' => $tipe ?: null, 'keterangan' => $keterangan,
                            ]);
                        }
                    } else {
                        // Fallback: no region columns, store all remaining cols as one
                        $allItems = array_filter(array_map('trim', array_slice($row, 2)), fn($v) => $v !== '');
                        $tampung([
                            'kode' => $nosin, 'wilayah' => null,
                            'nama' => $tipe ?: null, 'keterangan' => implode(', ', $allItems) ?: null,
                        ]);
                    }
                    // Dihitung per baris SUMBER, bukan per baris tersimpan --
                    // satu baris berkas perlengkapan bisa menghasilkan sampai
                    // tiga baris (satu per wilayah), dan angka yang dilaporkan
                    // ke pengguna tetap jumlah baris yang ia lihat di berkasnya.
                    $imported++;
                    continue;
                }

                $data = [];
                $casts = (new $model())->getCasts();
                foreach ($cols as $i => $col) {
                    $val = isset($row[$i]) ? trim((string) $row[$i]) : null;
                    if ($val === '') {
                        $val = null;
                    } elseif (isset($casts[$col]) && in_array($casts[$col], ['float', 'double', 'decimal', 'integer', 'int'])) {
                        // Tangani format angka Indonesia: koma sebagai desimal, hapus karakter non-numerik
                        $numVal = str_replace(',', '.', $val);
                        $numVal = preg_replace('/[^0-9.\-]/', '', $numVal);
                        $val = is_numeric($numVal) ? $numVal : null;
                    }
                    $data[$col] = $val;
                }
                // Override jenis for MT import
                if ($mtJenis !== null && $mtJenis !== '') {
                    $data['jenis'] = $mtJenis;
                }
                $tampung($data);
                $imported++;
            }

            // Sisa tampungan terakhir yang belum mencapai satu potongan penuh.
            $this->simpanMassal($model, $type, $baris);
        });

        $logger->write($request, 'DB_IMPORT', $type, "Import {$imported} data ke database: {$type}", $request->user());

        return response()->json([
            'ok'       => true,
            'message'  => "{$imported} data berhasil diimport.",
            'imported' => $imported,
        ]);
    }

    /**
     * Tulis kumpulan baris hasil import ke database.
     *
     * Jalur cepat (upsert per potongan) HANYA dipakai kalau tabelnya benar-benar
     * punya indeks unik yang persis menutupi kunci import-nya. Tanpa indeks itu
     * upsert tidak punya dasar untuk mengenali baris yang sudah ada, dan diam-diam
     * berubah jadi insert biasa -- datanya berganda tiap kali berkas yang sama
     * diunggah ulang. db_perlengkapan contohnya: kunci import-nya kode+wilayah
     * tapi tabelnya belum punya indeks itu, jadi ia tetap lewat jalur lama yang
     * aman (dan berkasnya memang kecil, jadi tidak rugi kecepatan).
     *
     * Pengecekannya dilakukan saat jalan, bukan dari daftar yang ditulis tangan
     * di sini: begitu indeks yang kurang ditambahkan lewat migration, tabelnya
     * ikut cepat sendiri tanpa perlu ada yang ingat menyunting berkas ini.
     */
    private function simpanMassal(string $model, string $type, array $baris): void
    {
        if (empty($baris)) {
            return;
        }

        $uniqueKeys = self::$uniqueKeys[$type] ?? [];
        $tabel      = (new $model())->getTable();

        if (empty($uniqueKeys) || ! $this->punyaIndeksUnik($tabel, $uniqueKeys)) {
            $this->simpanSatuSatu($model, $uniqueKeys, $baris);

            return;
        }

        $siapBatch = [];
        $sisa      = [];

        foreach ($baris as $data) {
            $nilaiKunci = array_map(fn ($k) => $data[$k] ?? null, $uniqueKeys);

            // Baris yang salah satu nilai kuncinya null dikembalikan ke jalur
            // lama: di MySQL NULL tidak pernah dianggap sama dengan NULL,
            // sehingga indeks unik TIDAK mencegah duplikat untuk baris seperti
            // ini -- sementara updateOrCreate mencocokkannya lewat "is null"
            // dan tetap menimpa baris yang sama, persis seperti selama ini.
            if (in_array(null, $nilaiKunci, true)) {
                $sisa[] = $data;
                continue;
            }

            // Baris ganda DI DALAM satu berkas digabung di sini, yang terakhir
            // menang -- sama seperti updateOrCreate yang menimpa berulang kali.
            // Ini bukan sekadar kerapian: satu perintah upsert tidak boleh
            // menyentuh baris yang sama dua kali (MySQL menolaknya). Berkas HET
            // nyata memang berisi 6 kodepart kembar.
            $siapBatch[implode("\0", $nilaiKunci)] = $data;
        }

        if (! empty($siapBatch)) {
            // Semua baris harus punya susunan kolom yang sama: satu perintah
            // upsert menyimpulkan daftar kolomnya dari baris pertama saja, jadi
            // baris yang kekurangan kolom akan menggeser nilai baris lain.
            $kolom = [];
            foreach ($siapBatch as $data) {
                $kolom += array_flip(array_keys($data));
            }
            $kolom  = array_keys($kolom);
            $kosong = array_fill_keys($kolom, null);

            $isi = [];
            foreach ($siapBatch as $data) {
                $isi[] = array_replace($kosong, $data);
            }

            $kolomUpdate = array_values(array_diff($kolom, $uniqueKeys));

            // Tiap baris menyumbang satu set placeholder ke query, dan MySQL
            // membatasi 65.535 placeholder per perintah. Ditahan di sekitar
            // 4.000 supaya tetap jauh dari batas berapa pun lebar tabelnya;
            // +2 untuk created_at/updated_at yang ditambahkan Eloquent sendiri.
            $potong = max(50, intdiv(4000, count($kolom) + 2));

            foreach (array_chunk($isi, $potong) as $potongan) {
                $model::upsert($potongan, $uniqueKeys, $kolomUpdate);
            }
        }

        $this->simpanSatuSatu($model, $uniqueKeys, $sisa);
    }

    private function simpanSatuSatu(string $model, array $uniqueKeys, array $baris): void
    {
        foreach ($baris as $data) {
            $keyData = array_intersect_key($data, array_flip($uniqueKeys));
            $valData = array_diff_key($data, array_flip($uniqueKeys));

            if (! empty($keyData)) {
                $model::updateOrCreate($keyData, $valData);
            } else {
                $model::create($data);
            }
        }
    }

    /** Adakah indeks unik yang kolomnya PERSIS $keys (tidak kurang, tidak lebih)? */
    private function punyaIndeksUnik(string $tabel, array $keys): bool
    {
        $dicari = array_map('strtolower', $keys);
        sort($dicari);

        $ingatan = $tabel . ':' . implode(',', $dicari);
        if (isset($this->cacheIndeksUnik[$ingatan])) {
            return $this->cacheIndeksUnik[$ingatan];
        }

        foreach (Schema::getIndexes($tabel) as $indeks) {
            if (empty($indeks['unique'])) {
                continue;
            }

            $kolom = array_map('strtolower', $indeks['columns']);
            sort($kolom);

            if ($kolom === $dicari) {
                return $this->cacheIndeksUnik[$ingatan] = true;
            }
        }

        return $this->cacheIndeksUnik[$ingatan] = false;
    }

    /**
     * Posisi kolom berkas import MT, dibaca dari ISI baris header (bukan
     * indeks tetap) — dua template nyata yang sempat diunggah auditor untuk
     * berkas "sama" (Database MT) sudah cukup membuktikan urutan/kehadiran
     * kolomnya tidak bisa diasumsikan stabil:
     *   No. | Nama Singkat | (kosong) | Nama Peralatan (IND) | Kode Peralatan | Gambar | Harga
     * Kolom "Gambar" di situ bukan sekadar berbeda posisi — bisa hadir atau
     * tidak — sehingga Harga ikut bergeser. Dengan membaca posisi dari label
     * headernya sendiri, berapa pun kolom tambahan di antaranya (atau urutan
     * kolom yang tertukar) tetap terbaca benar.
     *
     * @return array{nomor:?int,nama_singkat:?int,nama_peralatan:?int,kode_peralatan:?int,harga:?int}
     *   Semua null kalau baris yang diberikan bukan header (mis. berkas tanpa
     *   header sama sekali, baris pertama sudah data) — pemanggil jatuh ke
     *   posisi lama yang sudah dikenal (0,1,3,4, harga tidak ada).
     */
    private function mtLabelUntukSel(string $n): ?string
    {
        if ($n === '') return null;
        if ($n === 'no' || $n === 'no.' || $n === 'nomor') return 'nomor';
        if (str_contains($n, 'nama singkat')) return 'nama_singkat';
        if (str_contains($n, 'nama peralatan')) return 'nama_peralatan';
        if (str_contains($n, 'kode peralatan')) return 'kode_peralatan';
        if (str_contains($n, 'harga')) return 'harga';
        return null;
    }

    private function detectMtColumns(array $headerRow): array
    {
        $posisi = ['nomor' => null, 'nama_singkat' => null, 'nama_peralatan' => null, 'kode_peralatan' => null, 'harga' => null];
        $adaHeader = false;

        foreach ($headerRow as $ci => $cell) {
            $field = $this->mtLabelUntukSel(strtolower(trim((string) $cell)));
            if ($field !== null) { $posisi[$field] = $ci; $adaHeader = true; }
        }

        if (!$adaHeader) {
            // Tanpa header dikenali sama sekali — pola lama sebelum Harga ada:
            // No.(0) | Nama Singkat(1) | kosong(2) | Nama Peralatan(3) | Kode Peralatan(4).
            return ['nomor' => 0, 'nama_singkat' => 1, 'nama_peralatan' => 3, 'kode_peralatan' => 4, 'harga' => null];
        }

        return $posisi;
    }

    /**
     * Cari indeks baris header MT, melompati baris kosong (pemisah) di
     * atasnya. Kalau baris non-kosong pertama yang ditemukan bukan header
     * (tidak ada label yang dikenali), berkasnya dianggap memang tanpa
     * header — baris itu sudah data, kembalikan null.
     */
    private function mtCariIndeksHeader(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 10, true) as $ri => $row) {
            $trimmed = array_map(fn($c) => trim((string) $c), $row);
            if (empty(array_filter($trimmed))) continue;

            foreach ($trimmed as $cell) {
                if ($this->mtLabelUntukSel(strtolower($cell)) !== null) {
                    return $ri;
                }
            }
            return null;
        }
        return null;
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Tidak dapat membaca file CSV.');
        }
        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiters = [',', ';', "\t", '|'];
        $counts = array_map(fn($d) => substr_count($firstLine, $d), $delimiters);
        $delimiter = $delimiters[array_search(max($counts), $counts)];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException('Tidak dapat membaca file Excel.');
        }

        // Find End of Central Directory record
        $eocdPos = strrpos($data, "\x50\x4b\x05\x06");
        if ($eocdPos === false) {
            throw new \RuntimeException('File Excel tidak valid atau rusak.');
        }

        $cdOffset = unpack('V', substr($data, $eocdPos + 16, 4))[1];
        $cdSize   = unpack('V', substr($data, $eocdPos + 12, 4))[1];

        // Parse Central Directory to build file index
        $files = [];
        $pos   = $cdOffset;
        while ($pos < $cdOffset + $cdSize) {
            if (substr($data, $pos, 4) !== "\x50\x4b\x01\x02") break;
            $compMethod  = unpack('v', substr($data, $pos + 10, 2))[1];
            $compSize    = unpack('V', substr($data, $pos + 20, 4))[1];
            $uncompSize  = unpack('V', substr($data, $pos + 24, 4))[1];
            $fnLen       = unpack('v', substr($data, $pos + 28, 2))[1];
            $extraLen    = unpack('v', substr($data, $pos + 30, 2))[1];
            $commentLen  = unpack('v', substr($data, $pos + 32, 2))[1];
            $localOffset = unpack('V', substr($data, $pos + 42, 4))[1];
            $fileName    = substr($data, $pos + 46, $fnLen);
            $files[$fileName] = compact('compMethod', 'compSize', 'uncompSize', 'localOffset');
            $pos += 46 + $fnLen + $extraLen + $commentLen;
        }

        $extract = function (string $name) use ($data, $files): ?string {
            if (!isset($files[$name])) return null;
            $info  = $files[$name];
            $lPos  = $info['localOffset'];
            if (substr($data, $lPos, 4) !== "\x50\x4b\x03\x04") return null;
            $fnLen    = unpack('v', substr($data, $lPos + 26, 2))[1];
            $extraLen = unpack('v', substr($data, $lPos + 28, 2))[1];
            $raw = substr($data, $lPos + 30 + $fnLen + $extraLen, $info['compSize']);
            return $info['compMethod'] === 0 ? $raw : gzinflate($raw);
        };

        // Parse shared strings
        $sharedStrings = [];
        $ssContent = $extract('xl/sharedStrings.xml');
        if ($ssContent !== null) {
            $ss = simplexml_load_string($ssContent);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r ?? [] as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetContent = $extract('xl/worksheets/sheet1.xml');
        if ($sheetContent === null) {
            throw new \RuntimeException('Sheet tidak ditemukan di dalam file Excel.');
        }

        // Isi berkas mentah, indeks zip-nya, dan XML sharedStrings sudah tidak
        // dipakai lagi setelah dua bagian di atas diambil. Dibebaskan sekarang,
        // bukan dibiarkan sampai method selesai: pada berkas HET nyata (63.049
        // baris) ketiganya menahan belasan MB percuma justru selama bagian yang
        // paling haus memori — penguraian sheet dan penyusunan baris di bawah.
        // $extract ikut dilepas karena closure-nya memegang $data.
        unset($data, $files, $extract, $ssContent);

        $sheet = simplexml_load_string($sheetContent);
        unset($sheetContent);
        $rows  = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            $lastIdx = -1;

            foreach ($row->c as $cell) {
                preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                $colStr = $m[1] ?? 'A';
                $colIdx = 0;
                foreach (str_split($colStr) as $ch) {
                    $colIdx = $colIdx * 26 + (ord($ch) - ord('A') + 1);
                }
                $colIdx--;

                while ($lastIdx < $colIdx - 1) {
                    $rowData[] = '';
                    $lastIdx++;
                }

                $cellType = (string) $cell['t'];
                $value    = (string) ($cell->v ?? '');

                if ($cellType === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($cellType === 'b') {
                    $value = $value ? 'TRUE' : 'FALSE';
                }

                $rowData[] = $value;
                $lastIdx   = $colIdx;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}
