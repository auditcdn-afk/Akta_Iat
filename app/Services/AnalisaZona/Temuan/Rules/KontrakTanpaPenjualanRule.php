<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaAccContract;
use App\Models\AnalisaLpkPenjualan;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Kontrak pembiayaan (baris tipe "1" di .ACC) yang nomor buktinya tidak
 * muncul sama sekali di LPK. Normalnya setiap kontrak punya pasangan baris
 * penjualan di LPK dengan no_bukti yang sama — pada data nyata SOTDB,
 * 18 dari 18 baris LPK jenis PBBO ketemu pasangannya di ACC, jadi
 * hubungannya memang rapat.
 *
 * Kontrak tanpa pasangan berarti unit tercatat terjual di sistem pembiayaan
 * tapi tidak muncul di laporan penjualan hari itu — bisa jadi memang beda
 * tanggal, tapi bisa juga penjualan yang tidak dilaporkan.
 */
class KontrakTanpaPenjualanRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'kontrak-tanpa-penjualan';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $kontrak = AnalisaAccContract::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('no_bukti')
            ->where('no_bukti', '!=', '')
            ->get();

        if ($kontrak->isEmpty()) {
            return [];
        }

        // Nomor bukti LPK dikumpulkan untuk SELURUH periode, bukan per hari —
        // penjualan bisa saja dilaporkan di tanggal yang berbeda dari tanggal
        // kontraknya, dan itu tidak dengan sendirinya janggal.
        $buktiLpk = AnalisaLpkPenjualan::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('no_bukti')
            ->pluck('no_bukti')
            ->filter()
            ->unique()
            ->flip();

        $yatim = $kontrak->filter(fn(AnalisaAccContract $c) => !$buktiLpk->has($c->no_bukti))->values();

        if ($yatim->isEmpty()) {
            return [];
        }

        $total = (float) $yatim->sum('harga_otr');

        return [Temuan::sedang(
            judul: sprintf(
                '%d kontrak pembiayaan tidak punya baris penjualan di LPK (total OTR Rp %s)',
                $yatim->count(),
                number_format($total, 0, ',', '.')
            ),
            rekomendasi: 'Cek ke cabang apakah unit ini benar sudah diserahkan dan penjualannya dilaporkan di tanggal lain, atau memang belum masuk laporan penjualan sama sekali.',
            nominal: $total,
            detail: [
                'items' => $yatim->map(fn(AnalisaAccContract $c) => [
                    'tanggal'       => $c->tanggal?->toDateString(),
                    'no_bukti'      => $c->no_bukti,
                    'kode_konsumen' => $c->kode_konsumen,
                    'harga_otr'     => (float) $c->harga_otr,
                    'cara_bayar'    => $c->cara_bayar,
                    'status_kredit' => $c->status_kredit,
                ])->all(),
            ],
        )];
    }
}
