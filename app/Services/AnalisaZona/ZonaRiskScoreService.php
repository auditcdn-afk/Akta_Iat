<?php

namespace App\Services\AnalisaZona;

use App\Models\AnalisaAccConsumer;
use App\Models\AnalisaAccContract;
use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaZonaScore;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung skor risiko per unit usaha (zona) per periode (bulan), dari
 * 4 indikator: kas kecil (RKK), pola pembiayaan konsumen (ACC kontrak),
 * penjualan & piutang belum lunas (LPK + ACC piutang), dan anomali/duplikasi
 * data. Tiap indikator dinormalisasi 0-100 relatif terhadap unit usaha lain
 * di periode yang sama (bukan skala absolut), lalu digabung dengan bobot.
 *
 * Bobot awal (bisa disesuaikan setelah dilihat hasilnya di data nyata):
 *   kas kecil 20% · pembiayaan 20% · penjualan & piutang 40% · anomali 20%
 * Piutang diberi bobot terbesar karena paling langsung menjawab pertanyaan
 * "zona mana yang perlu sering dikunjungi" — makin banyak & makin lama
 * piutang menumpuk, makin tinggi risikonya.
 */
class ZonaRiskScoreService
{
    private const BOBOT_KAS_KECIL          = 0.20;
    private const BOBOT_PEMBIAYAAN         = 0.20;
    private const BOBOT_PENJUALAN_PIUTANG  = 0.40;
    private const BOBOT_ANOMALI            = 0.20;

    public function recompute(string $periode): int
    {
        [$start, $end] = $this->periodeRange($periode);

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
            ];
        }

        $skorKasKecil  = $this->normalisasi(array_column($raw, 'kas_kecil'), fn($m) => $m['nominal_total']);
        $skorPembiayaan = $this->normalisasi(array_column($raw, 'pembiayaan'), fn($m) => $m['dp_tipis_ratio'] * 100 + $m['jumlah_kontrak']);
        $skorPenjualanPiutang = $this->normalisasi(array_column($raw, 'penjualan_piutang'), fn($m) => $m['piutang_nominal']);
        $skorAnomali = $this->normalisasi(array_column($raw, 'anomali'), fn($m) => $m['jumlah_duplikat']);

        $count = 0;
        $i = 0;
        foreach ($unitUsahaCodes as $kode) {
            $sKas   = $skorKasKecil[$i] ?? 0;
            $sPemb  = $skorPembiayaan[$i] ?? 0;
            $sJual  = $skorPenjualanPiutang[$i] ?? 0;
            $sAnom  = $skorAnomali[$i] ?? 0;
            $i++;

            $total = round(
                $sKas * self::BOBOT_KAS_KECIL
                + $sPemb * self::BOBOT_PEMBIAYAAN
                + $sJual * self::BOBOT_PENJUALAN_PIUTANG
                + $sAnom * self::BOBOT_ANOMALI,
                2
            );

            AnalisaZonaScore::updateOrCreate(
                ['unit_usaha_code' => $kode, 'periode' => $periode],
                [
                    'skor_kas_kecil'          => round($sKas, 2),
                    'skor_pembiayaan'         => round($sPemb, 2),
                    'skor_penjualan_piutang'  => round($sJual, 2),
                    'skor_anomali'            => round($sAnom, 2),
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
     * Normalisasi min-max ke skala 0-100. Kalau semua nilai sama (mis. cuma
     * 1 zona di data atau semuanya nol), semua diberi skor 50 supaya tidak
     * menyesatkan (bukan otomatis 0 atau 100).
     *
     * @param array<int, array> $rows
     * @param callable $extractor
     * @return array<int, float>
     */
    private function normalisasi(array $rows, callable $extractor): array
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

    private function periodeRange(string $periode): array
    {
        $start = $periode . '-01';
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }
}
