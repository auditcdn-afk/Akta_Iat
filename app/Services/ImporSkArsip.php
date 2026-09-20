<?php

namespace App\Services;

use App\Models\SuratKeputusan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

/**
 * Pemindahan arsip SK dari aplikasi lama (AppSheet) ke aplikasi ini.
 *
 * SK yang dipindahkan adalah BERKAS RIWAYAT, bukan pekerjaan yang masih
 * berjalan: tidak masuk alur persetujuan, tidak didistribusikan ulang, dan
 * tidak menagih pembebanan. Tujuannya supaya perpindahan aplikasi tidak
 * memutus rujukan ke keputusan tahun-tahun sebelumnya.
 *
 * Sumbernya dua berkas, yang kedua opsional:
 *   - ZIP berisi PDF SK. Nama berkasnya memuat nomor SK dan unit usaha,
 *     mis. "CDN.SK.2025.005.IAT CSC TDB.pdf".
 *   - Ekspor tabel SK dari aplikasi lama (xlsx/csv). Dipakai melengkapi
 *     No SPT dan poin Memutuskan, dicocokkan lewat nomor SK.
 */
class ImporSkArsip
{
    /** Awalan nama unit usaha / departemen pada nama berkas. */
    private const AWALAN_UNIT = [
        'CSC', 'SO', 'WHS', 'HM', 'HMS', 'KMS', 'POS', 'ACC', 'HRD', 'MT', 'PJD', 'RAC', 'IAT',
    ];

    public function __construct(
        /** @var array<string, array{no_spt: ?string, memutuskan: ?string, relation: string[]}> */
        private array $meta = [],
        /** @var string[] daftar unit usaha dari master, dipakai mengenali nama berkas */
        private array $masterUnit = [],
    ) {}

    public static function untuk(?string $metaPath = null): self
    {
        return new self(
            $metaPath ? self::bacaMeta($metaPath) : [],
            self::masterUnitUsaha(),
        );
    }

    /**
     * Baca isi ZIP menjadi daftar calon SK, tanpa menyentuh database.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pratinjau(string $zipPath): array
    {
        $baris = [];

        foreach ($this->berkasPdf($zipPath) as $nama => $isi) {
            $baris[] = $this->bacaSatuBerkas($nama, $isi);
        }

        usort($baris, fn($a, $b) => strcmp((string) $a['no_sk'], (string) $b['no_sk']));

        return $baris;
    }

    /**
     * Simpan calon SK yang berstatus "baru". Yang nomornya sudah ada di
     * database dilewati -- impor ulang berkas yang sama tidak menggandakan
     * arsip, dan tidak menimpa SK yang sedang berjalan.
     *
     * @return array{disimpan: int, dilewati: int, gagal: int, baris: array<int, array<string, mixed>>}
     */
    public function simpan(string $zipPath, ?string $olehUsername, ?string $olehNama): array
    {
        $hasil = ['disimpan' => 0, 'dilewati' => 0, 'gagal' => 0, 'baris' => []];

        foreach ($this->berkasPdf($zipPath) as $nama => $isi) {
            $baris = $this->bacaSatuBerkas($nama, $isi);

            if ($baris['keadaan'] !== 'baru') {
                $hasil[$baris['keadaan'] === 'gagal' ? 'gagal' : 'dilewati']++;
                $hasil['baris'][] = $baris;
                continue;
            }

            try {
                DB::transaction(function () use ($baris, $isi, $olehUsername, $olehNama) {
                    $path = 'sk/' . $this->namaAman($baris['no_sk']) . '-' . substr(md5($baris['berkas']), 0, 8) . '.pdf';
                    Storage::disk('public')->put($path, $isi);

                    SuratKeputusan::query()->create([
                        'no_sk'            => $baris['no_sk'],
                        'no_spt'           => $baris['no_spt'],
                        'unit_usaha'       => $baris['unit_usaha'],
                        'memutuskan'       => $baris['memutuskan'],
                        'file_sk'          => [
                            'name' => $baris['berkas'],
                            'type' => 'application/pdf',
                            'url'  => Storage::url($path),
                            'path' => $path,
                        ],
                        // Arsip: sudah selesai di aplikasi lama, tidak ditagih
                        // persetujuan maupun pembebanan lagi.
                        'status'           => 'selesai',
                        'perlu_pembebanan' => false,
                        'steps'            => ['migrasi' => array_filter([
                            'dari'     => 'AppSheet',
                            'berkas'   => $baris['berkas'],
                            'relation' => $baris['relation'] ?: null,
                            'pada'     => now()->toDateTimeString(),
                            'oleh'     => $olehNama ?: $olehUsername,
                        ])],
                        'uploaded_by'      => $olehUsername,
                        'uploaded_by_name' => $olehNama,
                        'uploaded_at'      => now(),
                    ]);
                });

                $this->catatTerdaftar($baris['no_sk']);
                $hasil['disimpan']++;
            } catch (Throwable $e) {
                $baris['keadaan'] = 'gagal';
                $baris['catatan'] = 'Gagal disimpan: ' . $e->getMessage();
                $hasil['gagal']++;
            }

            $hasil['baris'][] = $baris;
        }

        usort($hasil['baris'], fn($a, $b) => strcmp((string) $a['no_sk'], (string) $b['no_sk']));

        return $hasil;
    }

    /**
     * Isi ZIP, hanya berkas PDF, tanpa folder sistem dari macOS/Windows.
     *
     * @return array<string, string> nama berkas => isinya
     */
    private function berkasPdf(string $zipPath): array
    {
        $zip = new ZipArchive();

        abort_unless($zip->open($zipPath) === true, 422, 'Berkas ZIP tidak bisa dibuka.');

        $berkas = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = (string) $zip->getNameIndex($i);

            if (str_ends_with($nama, '/') || str_contains($nama, '__MACOSX') || basename($nama)[0] === '.') {
                continue;
            }

            if (strtolower(pathinfo($nama, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $isi = $zip->getFromIndex($i);

            if ($isi !== false) {
                $berkas[basename($nama)] = $isi;
            }
        }

        $zip->close();

        abort_if($berkas === [], 422, 'Tidak ada berkas PDF di dalam ZIP ini.');

        return $berkas;
    }

    /** @return array<string, mixed> */
    private function bacaSatuBerkas(string $nama, string $isi): array
    {
        $tanpaEkstensi = pathinfo($nama, PATHINFO_FILENAME);
        [$noSk, $unit] = $this->pisahNomorDanUnit($tanpaEkstensi);

        $meta = $this->meta[self::kunci($noSk)] ?? null;

        $baris = [
            'berkas'     => $nama,
            'no_sk'      => $noSk,
            'unit_usaha' => $unit ?: ($meta['relation'][0] ?? null),
            'no_spt'     => $meta['no_spt'] ?? null,
            'memutuskan' => $meta['memutuskan'] ?? $this->memutuskanDariPdf($isi),
            'relation'   => $meta['relation'] ?? [],
            'keadaan'    => 'baru',
            'catatan'    => null,
        ];

        if ($noSk === '') {
            $baris['keadaan'] = 'gagal';
            $baris['catatan'] = 'Nomor SK tidak terbaca dari nama berkas.';

            return $baris;
        }

        if ($this->sudahAda($noSk)) {
            $baris['keadaan'] = 'sudah ada';
            $baris['catatan'] = 'Nomor SK ini sudah ada di aplikasi, jadi dilewati.';

            return $baris;
        }

        if (!$baris['memutuskan']) {
            $baris['catatan'] = 'Poin Memutuskan tidak terbaca (PDF hasil pindai?). Berkas PDF-nya tetap tersimpan.';
        } elseif (self::tanpaSpasi($baris['memutuskan'])) {
            $baris['catatan'] = 'Lapisan teks PDF ini tanpa spasi, jadi poin Memutuskan menyambung. '
                . 'Sertakan ekspor tabel SK kalau ingin teksnya rapi.';
        }

        return $baris;
    }

    /**
     * Pisahkan "CDN.SK.2025.005.IAT CSC TDB" menjadi nomor SK dan unit usaha.
     * Nama unit dari master didahulukan supaya penamaan yang dipakai auditor
     * yang menang, bukan tebakan pola.
     *
     * @return array{0: string, 1: ?string}
     */
    private function pisahNomorDanUnit(string $namaBerkas): array
    {
        $namaBerkas = trim(preg_replace('/\s+/', ' ', $namaBerkas));

        foreach ($this->masterUnit as $unit) {
            if (mb_strtolower(mb_substr($namaBerkas, -mb_strlen($unit))) === mb_strtolower($unit)) {
                return [trim(mb_substr($namaBerkas, 0, -mb_strlen($unit)), " .-_"), $unit];
            }
        }

        $awalan = implode('|', self::AWALAN_UNIT);

        if (preg_match('/^(?<no>.+?)[ _-]+(?<unit>(?:' . $awalan . ')\s+[A-Za-z][\w.]*)$/u', $namaBerkas, $m)) {
            return [trim($m['no'], " .-_"), trim($m['unit'])];
        }

        return [trim($namaBerkas, " .-_"), null];
    }

    private function memutuskanDariPdf(string $isi): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sk-arsip-');

        try {
            file_put_contents($tmp, $isi);

            return SkMemutuskanExtractor::extractFromPath($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** @var array<string, true>|null kunci nomor SK yang sudah ada di database */
    private ?array $sudahTerdaftar = null;

    private function sudahAda(string $noSk): bool
    {
        $this->sudahTerdaftar ??= SuratKeputusan::query()
            ->pluck('no_sk')
            ->mapWithKeys(fn($no) => [self::kunci((string) $no) => true])
            ->all();

        return isset($this->sudahTerdaftar[self::kunci($noSk)]);
    }

    private function catatTerdaftar(string $noSk): void
    {
        $this->sudahTerdaftar[self::kunci($noSk)] = true;
    }

    /** @param string[] $kepala */
    private static function cariKolom(array $kepala, array $nama): ?int
    {
        foreach ($nama as $cari) {
            $i = array_search($cari, $kepala, true);

            if ($i !== false) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * Nomor SK ditulis berbeda-beda di aplikasi lama: "CDN-SK/2024.225/IAT",
     * "CDN.SK.2024.231.IAT", "025/HM-D/2024". Pencocokan memakai huruf dan
     * angkanya saja supaya ketiganya tetap bertemu dengan pasangannya.
     */
    /**
     * Sebagian PDF mengekstrak teks tanpa spasi sama sekali
     * ("StockAHMOil'syangberselisih..."). Masih terbaca manusia, tapi layak
     * ditandai supaya bisa diganti dengan teks dari ekspor tabel.
     *
     * Batasnya jelas pada berkas asli: SK yang normal kata terpanjangnya 11-15
     * huruf, yang tanpa spasi 83-84.
     */
    private static function tanpaSpasi(string $teks): bool
    {
        $kata = preg_split('/\s+/', trim($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($kata as $satu) {
            if (mb_strlen($satu) > 30) {
                return true;
            }
        }

        return false;
    }

    public static function kunci(string $noSk): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $noSk) ?? '');
    }

    private function namaAman(string $noSk): string
    {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '-', $noSk), '-') ?: 'sk';
    }

    /** @return string[] */
    private static function masterUnitUsaha(): array
    {
        try {
            $unit = DB::table('db_unit_usaha')->pluck('unit_usaha')->all();
        } catch (Throwable) {
            return [];
        }

        $unit = array_values(array_filter(array_map('trim', $unit)));

        // Yang terpanjang lebih dulu supaya "WHS Part KIM" menang atas "WHS".
        usort($unit, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $unit;
    }

    /**
     * Ekspor tabel SK dari aplikasi lama. Satu nomor SK bisa punya beberapa
     * baris -- satu poin keputusan untuk satu pihak -- jadi poinnya disatukan
     * dan pihaknya dikumpulkan.
     *
     * @return array<string, array{no_spt: ?string, memutuskan: ?string, relation: string[]}>
     */
    public static function bacaMeta(string $path): array
    {
        try {
            $buku  = IOFactory::load($path);
            $sheet = $buku->getSheetByName('SK') ?? $buku->getActiveSheet();
            $rows  = $sheet->toArray(null, true, false, false);
        } catch (Throwable $e) {
            abort(422, 'Berkas ekspor tabel tidak terbaca: ' . $e->getMessage());
        }

        $kepala = array_map(
            fn($n) => strtolower(trim(preg_replace('/\s+/', ' ', (string) $n))),
            array_shift($rows) ?? []
        );

        $iNoSk  = self::cariKolom($kepala, ['no sk', 'nosk', 'no_sk']);
        $iSpt   = self::cariKolom($kepala, ['keputusanid', 'no spt', 'nospt', 'no_spt']);
        $iPoin  = self::cariKolom($kepala, ['keputusan', 'memutuskan']);
        $iPihak = self::cariKolom($kepala, ['relation', 'unit usaha', 'unit_usaha']);

        abort_if($iNoSk === null, 422, 'Kolom "No SK" tidak ditemukan pada berkas ekspor tabel.');

        $meta = [];

        foreach ($rows as $row) {
            $noSk = trim((string) ($row[$iNoSk] ?? ''));

            if ($noSk === '') {
                continue;
            }

            $kunci = self::kunci($noSk);
            $meta[$kunci] ??= ['no_spt' => null, 'memutuskan' => null, 'relation' => []];

            $spt = $iSpt !== null ? trim((string) ($row[$iSpt] ?? '')) : '';
            if ($spt !== '' && !$meta[$kunci]['no_spt']) {
                $meta[$kunci]['no_spt'] = mb_substr($spt, 0, 80);
            }

            $poin = $iPoin !== null ? trim((string) ($row[$iPoin] ?? '')) : '';
            if ($poin !== '' && !str_contains((string) $meta[$kunci]['memutuskan'], $poin)) {
                $meta[$kunci]['memutuskan'] = trim(($meta[$kunci]['memutuskan'] ?? '') . "\n" . $poin);
            }

            $pihak = $iPihak !== null ? trim((string) ($row[$iPihak] ?? '')) : '';
            if ($pihak !== '' && !in_array($pihak, $meta[$kunci]['relation'], true)) {
                $meta[$kunci]['relation'][] = $pihak;
            }
        }

        foreach ($meta as $kunci => $satu) {
            if ($satu['memutuskan']) {
                $meta[$kunci]['memutuskan'] = mb_substr($satu['memutuskan'], 0, 5000);
            }
        }

        return $meta;
    }
}
