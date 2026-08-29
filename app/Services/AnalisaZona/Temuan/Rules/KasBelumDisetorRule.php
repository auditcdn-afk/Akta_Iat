<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaPosisiKas;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Saldo akhir kas cabang yang tertahan di atas batas wajar. Kas yang menginap
 * di cabang adalah risiko fisik (hilang/terpakai) sekaligus tanda setoran ke
 * bank tidak tertib — salah satu hal pertama yang diperiksa auditor saat
 * berkunjung.
 *
 * Diperiksa PER HARI (bukan cuma hari terakhir) supaya pola "sering menahan
 * kas besar" tetap terlihat walau pada hari terakhir kebetulan sudah disetor.
 */
class KasBelumDisetorRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'kas-belum-disetor';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $ambang = (float) config('analisa_zona.temuan.saldo_kas_wajar_max');

        $lewat = AnalisaPosisiKas::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->where('saldo_akhir_kas', '>', $ambang)
            ->orderByDesc('saldo_akhir_kas')
            ->get();

        if ($lewat->isEmpty()) {
            return [];
        }

        $tertinggi = $lewat->first();

        return [Temuan::tinggi(
            judul: sprintf(
                'Kas menginap di atas Rp %s pada %d hari (tertinggi Rp %s tanggal %s)',
                number_format($ambang, 0, ',', '.'),
                $lewat->count(),
                number_format((float) $tertinggi->saldo_akhir_kas, 0, ',', '.'),
                $tertinggi->tanggal->toDateString()
            ),
            rekomendasi: 'Periksa fisik kas dan bukti setoran bank pada tanggal-tanggal tersebut, serta tanyakan alasan kas tidak disetor pada hari yang sama.',
            nominal: (float) $tertinggi->saldo_akhir_kas,
            tanggal: $tertinggi->tanggal->toDateString(),
            detail: [
                'ambang' => $ambang,
                'items'  => $lewat->map(fn(AnalisaPosisiKas $p) => [
                    'tanggal'         => $p->tanggal->toDateString(),
                    'saldo_akhir_kas' => (float) $p->saldo_akhir_kas,
                ])->all(),
            ],
        )];
    }
}
