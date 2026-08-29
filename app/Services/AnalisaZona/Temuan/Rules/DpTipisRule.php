<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaAccContract;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Kontrak dengan uang muka tipis dibanding harga OTR. DP kecil menaikkan
 * risiko kredit macet, dan kalau terpusat di sales tertentu bisa jadi tanda
 * pelonggaran syarat demi mengejar target.
 *
 * Kontrak tanpa harga OTR (nol) dilewati — bukan berarti DP-nya nol,
 * melainkan datanya tidak lengkap untuk dinilai.
 */
class DpTipisRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'dp-tipis';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $ambang = (float) config('analisa_zona.temuan.dp_ratio_min');

        $tipis = AnalisaAccContract::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->where('harga_otr', '>', 0)
            ->get()
            ->filter(fn(AnalisaAccContract $c) => ((float) $c->dp / (float) $c->harga_otr) < $ambang)
            ->sortBy(fn(AnalisaAccContract $c) => (float) $c->dp / (float) $c->harga_otr)
            ->values();

        if ($tipis->isEmpty()) {
            return [];
        }

        $terkecil = $tipis->first();
        $rasioTerkecil = (float) $terkecil->dp / (float) $terkecil->harga_otr;

        return [Temuan::sedang(
            judul: sprintf(
                '%d kontrak dengan DP di bawah %.0f%% dari harga OTR (terendah %.1f%%)',
                $tipis->count(),
                $ambang * 100,
                $rasioTerkecil * 100
            ),
            rekomendasi: 'Periksa kelengkapan berkas dan persetujuan kredit untuk kontrak-kontrak ini, dan lihat apakah terpusat pada sales tertentu.',
            nominal: (float) $tipis->sum('harga_otr'),
            detail: [
                'ambang_ratio' => $ambang,
                'items'        => $tipis->map(fn(AnalisaAccContract $c) => [
                    'tanggal'       => $c->tanggal?->toDateString(),
                    'no_bukti'      => $c->no_bukti,
                    'kode_konsumen' => $c->kode_konsumen,
                    'harga_otr'     => (float) $c->harga_otr,
                    'dp'            => (float) $c->dp,
                    'dp_ratio'      => round((float) $c->dp / (float) $c->harga_otr, 4),
                    'kode_sales'    => $c->kode_sales,
                    'cara_bayar'    => $c->cara_bayar,
                ])->all(),
            ],
        )];
    }
}
