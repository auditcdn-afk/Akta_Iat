<?php

namespace App\Services;

use App\Models\DbMt;
use App\Models\PemeriksaanMt;

/**
 * Satu sumber kebenaran untuk "REKAP TOOLS RUSAK" & "REKAP TOOLS HILANG" —
 * tabel per mekanik yang mencantumkan Kode Tool, Nama Tool, dan Harga (nilai
 * kerugian kalau tool itu rusak/hilang) untuk setiap alat yang statusnya
 * rusak atau hilang.
 *
 * Dipakai dua tempat yang harus tampil identik:
 *  1. Report Audit PDF, bagian setelah section MT.
 *  2. Tombol "Cetak Rusak & Hilang" di tab pemeriksaan MT sendiri (halaman
 *     cetak terpisah, tidak perlu membuka seluruh Report Audit).
 *
 * Data pemeriksaan (PemeriksaanMt::data_json) hanya menyimpan NAMA tool per
 * kategori (bagus/rusak/skAudit/hilang) — bukan kode atau harganya, itu
 * berasal dari katalog db_mt dan dicari lewat nama (di dalam jenis yang sama:
 * MT Baru/Lama/FI), karena satu nama tool bisa saja muncul di lebih dari satu
 * jenis dengan kode/harga berbeda.
 */
class MtRekapBuilder
{
    private const JENIS_MAP = ['baru' => 'MT Baru', 'lama' => 'MT Lama', 'fi' => 'MT FI'];

    /**
     * @return array{rusak: array<string, array>, hilang: array<string, array>}
     *   Tiap kategori: [mekanik => ['keterangan' => string, 'rows' => [
     *       ['kode' => string, 'nama' => string, 'harga' => float|null], ...
     *   ]]]. Mekanik tanpa baris di kategori itu tidak ikut muncul.
     */
    public function build(?PemeriksaanMt $mt): array
    {
        $entries = $this->entriesAktif($mt);
        if (!$entries) {
            return ['rusak' => [], 'hilang' => []];
        }

        // Katalog dimuat sekali per jenis yang benar-benar terpakai, dikunci
        // per nama (huruf kecil, tanpa spasi ganda) — bukan seluruh db_mt
        // sekaligus, supaya rekap tetap ringan walau katalognya besar.
        $jenisTerpakai = collect($entries)->pluck('jenis')->filter()->unique();
        $katalog = [];
        foreach ($jenisTerpakai as $jenisKey) {
            $label = self::JENIS_MAP[$jenisKey] ?? null;
            if (!$label) continue;
            $katalog[$jenisKey] = DbMt::where('jenis', $label)->get()
                ->keyBy(fn(DbMt $r) => $this->kunciNama($r->nama_peralatan ?: $r->nama_singkat));
        }

        $hasil = ['rusak' => [], 'hilang' => []];
        foreach ($entries as $entry) {
            $mekanik = trim((string) ($entry['mekanik'] ?? ''));
            if ($mekanik === '') continue;
            $jenisKey = $entry['jenis'] ?? 'baru';
            $peta = $katalog[$jenisKey] ?? collect();

            foreach (['rusak', 'hilang'] as $kategori) {
                $namaList = $entry[$kategori] ?? [];
                if (empty($namaList)) continue;

                $rows = [];
                foreach ($namaList as $nama) {
                    $row = $peta->get($this->kunciNama($nama));
                    $rows[] = [
                        'kode'  => $row?->kode_peralatan ?? '',
                        'nama'  => $nama,
                        'harga' => $row?->harga !== null ? (float) $row->harga : null,
                    ];
                }

                if (!isset($hasil[$kategori][$mekanik])) {
                    $hasil[$kategori][$mekanik] = ['keterangan' => trim((string) ($entry['keterangan'] ?? '')), 'rows' => []];
                }
                $hasil[$kategori][$mekanik]['rows'] = array_merge($hasil[$kategori][$mekanik]['rows'], $rows);
                // Beberapa mekanik punya lebih dari 1 entry aktif (jarang, tapi
                // struktur datanya memungkinkan) — keterangan diambil dari
                // entry pertama yang mengisinya, bukan ditimpa entry kosong.
                if ($hasil[$kategori][$mekanik]['keterangan'] === '' && !empty($entry['keterangan'])) {
                    $hasil[$kategori][$mekanik]['keterangan'] = trim((string) $entry['keterangan']);
                }
            }
        }

        return $hasil;
    }

    /** Berapa banyak baris (semua mekanik) di satu kategori — dipakai untuk "tampilkan kalau ada isinya". */
    public function totalBaris(array $rekapKategori): int
    {
        return array_sum(array_map(fn($m) => count($m['rows']), $rekapKategori));
    }

    private function kunciNama(mixed $nama): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $nama)));
    }

    /**
     * Entries yang aktif = jenis yang sedang dipilih auditor untuk mekanik itu
     * (mekanikSelectedJenis) — sama seperti filter section MT di Report Audit
     * PDF. Mekanik bisa punya entry di 3 jenis (Baru/Lama/FI) sekaligus; yang
     * dicetak hanya jenis yang sedang aktif dipilih, bukan ketiganya.
     */
    private function entriesAktif(?PemeriksaanMt $mt): array
    {
        $raw     = $mt?->data_json ?? [];
        $entries = $raw['entries'] ?? [];
        $selectedJenis = $raw['mekanikSelectedJenis'] ?? [];

        return array_values(array_filter($entries, function ($e) use ($selectedJenis) {
            $mekanik  = $e['mekanik'] ?? '';
            $selected = $selectedJenis[$mekanik] ?? 'baru';
            return ($e['jenis'] ?? '') === $selected;
        }));
    }
}
