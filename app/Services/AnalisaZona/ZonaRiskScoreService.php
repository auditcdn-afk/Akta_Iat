<?php

namespace App\Services\AnalisaZona;

use App\Models\AnalisaAccConsumer;
use App\Models\AnalisaAccContract;
use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaPosisiKas;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaZonaScore;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung skor risiko per unit usaha (zona) per periode (bulan), dari
 * 5 indikator: kas kecil (RKK), pola pembiayaan konsumen (ACC kontrak),
 * penjualan & piutang belum lunas (LPK + ACC piutang), anomali/duplikasi
 * data, dan posisi kas harian (LHPBK — saldo kas yang belum disetor bank).
 *
 * Bobot awal (bisa disesuaikan setelah dilihat hasilnya di data nyata):
 *   kas kecil 15% · pembiayaan 15% · penjualan & piutang 35% ·
 *   anomali 15% · posisi kas 20%
 * Piutang tetap diberi bobot terbesar karena paling langsung menjawab
 * pertanyaan "zona mana yang perlu sering dikunjungi".
 *
 * Tiap indikator dihitung dari DUA skor yang dirata-ratakan:
 *   - relatif: dibanding zona lain di periode yang sama (min-max 0-100)
 *   - absolut: dibanding ambang nominal tetap di config('analisa_zona.ambang')
 * Alasan dua-duanya (bukan relatif saja seperti versi awal): skor relatif
 * runtuh jadi rata 50 untuk SEMUA zona begitu cuma ada 1 zona yang punya
 * data di suatu periode (tidak ada pembanding) — kondisi nyata yang sering
 * terjadi selama adopsi upload rutin belum merata ke semua unit usaha. Skor
 * absolut tetap mencerminkan besar-kecilnya angka meski tidak ada zona lain
 * untuk dibandingkan, jadi skor akhir tidak lagi "datar" di kondisi itu.
 */
class ZonaRiskScoreService
{
    private const BOBOT_KAS_KECIL          = 0.15;
    private const BOBOT_PEMBIAYAAN         = 0.15;
    private const BOBOT_PENJUALAN_PIUTANG  = 0.35;
    private const BOBOT_ANOMALI            = 0.15;
    private const BOBOT_POSISI_KAS         = 0.20;

    public function recompute(string $periode): int
    {
        [$start, $end] = $this->periodeRange($periode);
        $ambang = config('analisa_zona.ambang');

        $unitUsahaCodes = $this->collectUnitUsahaCodes($start, $end);
        if ($unitUsahaCodes->isEmpty()) {
            return 0;
        }

        $raw = [];
        foreach ($unitUsahaCodes as $kode) {
            $raw[$kode] = [
                'kas_kecil'         => $this->metrikKasKecil($kode, $start, $end),
                'pembiayaan'        => $this->metrikPembiayaan($kode, $start, $end),
                'penjualan_piutang' => $this->metrikPenjualanPiutang($kode, $start, $end),
                'anomali'           => $this->metrikAnomali($kode, $start, $end),
                'posisi_kas'        => $this->metrikPosisiKas($kode, $start, $end),
            ];
        }

        $relKas  = $this->normalisasiRelatif(array_column($raw, 'kas_kecil'), fn($m) => $m['nominal_total']);
        $relPemb = $this->normalisasiRelatif(array_column($raw, 'pembiayaan'), fn($m) => $m['dp_tipis_ratio'] * 100 + $m['jumlah_kontrak']);
        $relJual = $this->normalisasiRelatif(array_column($raw, 'penjualan_piutang'), fn($m) => $m['piutang_nominal']);
        $relAnom = $this->normalisasiRelatif(array_column($raw, 'anomali'), fn($m) => $m['jumlah_duplikat']);
        $relKas2 = $this->normalisasiRelatif(array_column($raw, 'posisi_kas'), fn($m) => $m['saldo_akhir_kas_terakhir']);

        $count = 0;
        $idx = 0;
        foreach ($unitUsahaCodes as $kode) {
            $m = $raw[$kode];

            $absKas  = $this->skorAbsolut($m['kas_kecil']['nominal_total'], $ambang['kas_kecil_nominal_max']);
            $absPemb = round(min(100, $m['pembiayaan']['dp_tipis_ratio'] * 100), 2);
            $absJual = $this->skorAbsolut($m['penjualan_piutang']['piutang_nominal'], $ambang['piutang_nominal_max']);
            $absAnom = $this->skorAbsolut($m['anomali']['jumlah_duplikat'], $ambang['anomali_jumlah_max']);
            $absKas2 = $this->skorAbsolut($m['posisi_kas']['saldo_akhir_kas_terakhir'], $ambang['posisi_kas_saldo_max']);

            $relKasNilai  = $relKas[$idx];
            $relPembNilai = $relPemb[$idx];
            $relJualNilai = $relJual[$idx];
            $relAnomNilai = $relAnom[$idx];
            $relKas2Nilai = $relKas2[$idx];
            $idx++;

            $sKas  = round(($relKasNilai  + $absKas ) / 2, 2);
            $sPemb = round(($relPembNilai + $absPemb) / 2, 2);
            $sJual = round(($relJualNilai + $absJual) / 2, 2);
            $sAnom = round(($relAnomNilai + $absAnom) / 2, 2);
            $sKas2 = round(($relKas2Nilai + $absKas2) / 2, 2);

            $total = round(
                $sKas * self::BOBOT_KAS_KECIL
                + $sPemb * self::BOBOT_PEMBIAYAAN
                + $sJual * self::BOBOT_PENJUALAN_PIUTANG
                + $sAnom * self::BOBOT_ANOMALI
                + $sKas2 * self::BOBOT_POSISI_KAS,
                2
            );

            // skor_relatif/skor_absolut disimpan di detail_json murni untuk
            // transparansi (supaya kelihatan kenapa skor akhirnya segitu,
            // terutama saat cuma ada 1 zona dan skor relatif tidak berarti
            // apa-apa) — tidak dipakai di perhitungan lain.
            $raw[$kode]['skor_relatif'] = [
                'kas_kecil' => $relKasNilai, 'pembiayaan' => $relPembNilai,
                'penjualan_piutang' => $relJualNilai, 'anomali' => $relAnomNilai,
                'posisi_kas' => $relKas2Nilai,
            ];
            $raw[$kode]['skor_absolut'] = [
                'kas_kecil' => $absKas, 'pembiayaan' => $absPemb,
                'penjualan_piutang' => $absJual, 'anomali' => $absAnom,
                'posisi_kas' => $absKas2,
            ];

            AnalisaZonaScore::updateOrCreate(
                ['unit_usaha_code' => $kode, 'periode' => $periode],
                [
                    'skor_kas_kecil'          => round($sKas, 2),
                    'skor_pembiayaan'         => round($sPemb, 2),
                    'skor_penjualan_piutang'  => round($sJual, 2),
                    'skor_anomali'            => round($sAnom, 2),
                    'skor_posisi_kas'         => round($sKas2, 2),
                    'skor_total'              => $total,
                    'detail_json'             => $raw[$kode],
                    'computed_at'             => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function collectUnitUsahaCodes(string $start, string $end): \Illuminate\Support\Collection
    {
        $codes = collect();
        $codes = $codes->merge(AnalisaRkkTransaction::whereBetween('tanggal', [$start, $end])->distinct()->pluck('unit_usaha_code'));
        $codes = $codes->merge(AnalisaAccContract::whereBetween('tanggal', [$start, $end])->distinct()->pluck('unit_usaha_code'));
        $codes = $codes->merge(AnalisaLpkPenjualan::whereBetween('tanggal', [$start, $end])->distinct()->pluck('unit_usaha_code'));
        $codes = $codes->merge(AnalisaAccReceivable::whereBetween('tanggal_laporan', [$start, $end])->distinct()->pluck('unit_usaha_code'));
        $codes = $codes->merge(AnalisaPosisiKas::whereBetween('tanggal', [$start, $end])->distinct()->pluck('unit_usaha_code'));

        return $codes->filter()->unique()->values();
    }

    private function metrikKasKecil(string $kode, string $start, string $end): array
    {
        $q = AnalisaRkkTransaction::where('unit_usaha_code', $kode)->whereBetween('tanggal', [$start, $end]);
        return [
            'nominal_total' => (float) $q->sum('nominal'),
            'jumlah_baris'  => (int) $q->count(),
        ];
    }

    private function metrikPembiayaan(string $kode, string $start, string $end): array
    {
        $contracts = AnalisaAccContract::where('unit_usaha_code', $kode)->whereBetween('tanggal', [$start, $end])->get();
        $jumlah = $contracts->count();
        // DP "tipis" = rasio DP terhadap harga OTR di bawah 15% — ambang awal,
        // bisa disesuaikan setelah dilihat pola data nyata.
        $tipis  = $contracts->filter(function (AnalisaAccContract $c) {
            $ratio = $c->dp_ratio;
            return $ratio !== null && $ratio < 0.15;
        })->count();

        return [
            'jumlah_kontrak'  => $jumlah,
            'dp_tipis_jumlah' => $tipis,
            'dp_tipis_ratio'  => $jumlah > 0 ? round($tipis / $jumlah, 4) : 0.0,
        ];
    }

    private function metrikPenjualanPiutang(string $kode, string $start, string $end): array
    {
        $penjualan = (float) AnalisaLpkPenjualan::where('unit_usaha_code', $kode)
            ->whereBetween('tanggal', [$start, $end])
            ->where('kode_transaksi', '!=', 'CRGT')
            ->sum('nominal');

        $piutangQuery = AnalisaAccReceivable::where('unit_usaha_code', $kode)->whereBetween('tanggal_laporan', [$start, $end]);

        return [
            'penjualan_nominal' => $penjualan,
            'piutang_nominal'   => (float) $piutangQuery->sum('nominal'),
            'piutang_jumlah'    => (int) $piutangQuery->count(),
        ];
    }

    private function metrikAnomali(string $kode, string $start, string $end): array
    {
        // PENTING: duplikasi dicek per-upload (per file/hari), BUKAN lintas
        // hari dalam periode. Piutang yang sama (kode_konsumen+no_bukti) WAJAR
        // muncul berulang di file hari-hari berikutnya selama belum lunas —
        // itu bukan anomali, itu memang cara laporan piutang bekerja (snapshot
        // status setiap hari). Yang jadi sinyal anomali sebenarnya adalah baris
        // yang terduplikasi DALAM SATU file/hari yang sama (indikasi input
        // ganda saat proses di sistem sumbernya).
        $duplikatPiutang = AnalisaAccReceivable::where('unit_usaha_code', $kode)
            ->whereBetween('tanggal_laporan', [$start, $end])
            ->select('upload_id', 'kode_konsumen', 'no_bukti', DB::raw('COUNT(*) as jml'))
            ->groupBy('upload_id', 'kode_konsumen', 'no_bukti')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $duplikatKonsumen = AnalisaAccConsumer::where('unit_usaha_code', $kode)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('nik')
            ->where('nik', '!=', '')
            ->select('upload_id', 'nik', DB::raw('COUNT(*) as jml'))
            ->groupBy('upload_id', 'nik')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return [
            'jumlah_duplikat' => $duplikatPiutang->sum(fn($r) => $r->jml - 1) + $duplikatKonsumen->sum(fn($r) => $r->jml - 1),
        ];
    }

    /**
     * Posisi kas dari LHPBK — snapshot hari TERAKHIR yang ada datanya dalam
     * periode (bukan dijumlah seperti kas_kecil/piutang). Saldo akhir kas
     * adalah stok di satu titik waktu, bukan arus yang legal dijumlahkan
     * lintas hari; snapshot terbaru paling relevan untuk keputusan kunjungan
     * SEKARANG.
     */
    private function metrikPosisiKas(string $kode, string $start, string $end): array
    {
        $terbaru = AnalisaPosisiKas::where('unit_usaha_code', $kode)
            ->whereBetween('tanggal', [$start, $end])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();

        return [
            'saldo_akhir_kas_terakhir' => $terbaru ? (float) $terbaru->saldo_akhir_kas : 0.0,
            'tanggal_terakhir'         => $terbaru?->tanggal?->toDateString(),
            'jumlah_hari_data'         => AnalisaPosisiKas::where('unit_usaha_code', $kode)->whereBetween('tanggal', [$start, $end])->count(),
        ];
    }

    /**
     * Normalisasi min-max ke skala 0-100. Kalau semua nilai sama (mis. cuma
     * 1 zona di data atau semuanya nol), semua diberi skor 50 supaya tidak
     * menyesatkan (bukan otomatis 0 atau 100) — kondisi inilah yang jadi
     * alasan skor absolut (lihat skorAbsolut()) tetap dihitung terpisah dan
     * dirata-ratakan, supaya skor akhir tidak ikut runtuh jadi 50 semua.
     *
     * @param array<int, array> $rows
     * @param callable $extractor
     * @return array<int, float>
     */
    private function normalisasiRelatif(array $rows, callable $extractor): array
    {
        $values = array_map($extractor, $rows);
        if (empty($values)) {
            return [];
        }
        $min = min($values);
        $max = max($values);
        if ($max <= $min) {
            return array_fill(0, count($values), 50.0);
        }
        return array_map(fn($v) => round((($v - $min) / ($max - $min)) * 100, 2), $values);
    }

    /** Skor absolut: nilai riil dibanding ambang tetap, dibatasi maksimal 100. */
    private function skorAbsolut(float $value, float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }
        return round(min(100, max(0, $value) / $max * 100), 2);
    }

    private function periodeRange(string $periode): array
    {
        $start = $periode . '-01';
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }
}
