<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaPosisiKas;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Rekonsiliasi penerimaan unit: pos "KAS - Penerimaan Unit Uang Tunai"
 * (akun 21011) di LHPBK harus sama dengan jumlah SELURUH baris LPK pada
 * tanggal yang sama — LHPBK sendiri menulis "LPK tgl <tanggal>" sebagai
 * keterangan pos itu, jadi memang itu sumbernya.
 *
 * Pada data nyata SOTDB 26 Agustus 2026 keduanya cocok persis
 * (Rp 127.852.000). Penting: yang dijumlahkan adalah SEMUA jenis transaksi
 * LPK termasuk CRGT (kwitansi gantung) dan baris bernilai nol — bukan
 * penjualan unit baru saja. Menyaring jenis tertentu di sini akan membuat
 * rekonsiliasi yang sebenarnya cocok jadi terlihat selisih.
 */
class RekonPenerimaanLpkRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'rekon-penerimaan-lpk';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $toleransi = (float) config('analisa_zona.temuan.selisih_rekonsiliasi_toleransi');
        $temuan = [];

        $posisiKas = AnalisaPosisiKas::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->keyBy(fn(AnalisaPosisiKas $p) => $p->tanggal->toDateString());

        foreach ($posisiKas as $tanggal => $lhpbk) {
            $penerimaan = (float) $lhpbk->penerimaan_unit_tunai;

            $lpk = AnalisaLpkPenjualan::where('unit_usaha_code', $unitUsahaCode)
                ->whereDate('tanggal', $tanggal)
                ->get();

            if ($penerimaan == 0.0 && $lpk->isEmpty()) {
                continue;
            }

            $totalLpk = (float) $lpk->sum('nominal');
            $selisih  = $totalLpk - $penerimaan;

            if (abs($selisih) <= $toleransi) {
                continue;
            }

            $arah = $selisih > 0
                ? 'ada penjualan di LPK yang uangnya belum masuk catatan kas'
                : 'ada uang masuk di kas yang tidak ada baris penjualannya di LPK';

            $temuan[] = Temuan::tinggi(
                judul: sprintf(
                    'Selisih penerimaan unit %s: LPK Rp %s vs LHPBK Rp %s (beda Rp %s)',
                    $tanggal,
                    number_format($totalLpk, 0, ',', '.'),
                    number_format($penerimaan, 0, ',', '.'),
                    number_format(abs($selisih), 0, ',', '.')
                ),
                rekomendasi: sprintf(
                    'Telusuri transaksi tanggal %s satu per satu — %s. Mulai dari kwitansi bernominal besar dan kwitansi gantung (CRGT).',
                    $tanggal,
                    $arah
                ),
                nominal: abs($selisih),
                tanggal: $tanggal,
                detail: [
                    'total_lpk'        => $totalLpk,
                    'penerimaan_lhpbk' => $penerimaan,
                    'selisih'          => $selisih,
                    'jumlah_baris_lpk' => $lpk->count(),
                    'per_jenis'        => $lpk->groupBy('kode_transaksi')
                        ->map(fn($g) => ['jumlah' => $g->count(), 'nominal' => (float) $g->sum('nominal')])
                        ->all(),
                ],
            );
        }

        return $temuan;
    }
}
