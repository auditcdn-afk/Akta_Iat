<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaAccReceivable;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Piutang yang sudah lama belum cair, dihitung dari SNAPSHOT hari terakhir
 * (bukan gabungan semua hari) — file .ACC memuat ulang seluruh piutang yang
 * belum lunas setiap hari, jadi menjumlahkan lintas hari akan menghitung
 * piutang yang sama berkali-kali.
 *
 * Piutang di dealer motor umumnya tagihan ke perusahaan pembiayaan (FIF,
 * IMFI, CMD, dst) yang normalnya cair dalam hitungan hari; yang menggantung
 * lebih lama biasanya berkasnya bermasalah — dan itulah yang perlu dikejar
 * saat kunjungan.
 */
class PiutangMenunggakRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'piutang-menunggak';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $ambangHari    = (int) config('analisa_zona.temuan.piutang_umur_hari');
        $ambangNominal = (float) config('analisa_zona.temuan.piutang_nominal_min');

        $tanggalSnapshot = AnalisaAccReceivable::snapshotTerakhir($unitUsahaCode, $start, $end);

        if (!$tanggalSnapshot) {
            return [];
        }

        $menunggak = AnalisaAccReceivable::where('unit_usaha_code', $unitUsahaCode)
            ->whereDate('tanggal_laporan', $tanggalSnapshot)
            ->whereNotNull('tanggal_transaksi')
            ->where('nominal', '>=', $ambangNominal)
            ->get()
            ->filter(fn(AnalisaAccReceivable $r) => $r->tanggal_transaksi->diffInDays($r->tanggal_laporan) >= $ambangHari)
            ->sortByDesc(fn(AnalisaAccReceivable $r) => $r->tanggal_transaksi->diffInDays($r->tanggal_laporan))
            ->values();

        if ($menunggak->isEmpty()) {
            return [];
        }

        $total = (float) $menunggak->sum('nominal');
        $umurMaks = (int) $menunggak->max(fn(AnalisaAccReceivable $r) => $r->tanggal_transaksi->diffInDays($r->tanggal_laporan));

        return [Temuan::tinggi(
            judul: sprintf(
                '%d piutang belum cair lebih dari %d hari (total Rp %s, tertua %d hari)',
                $menunggak->count(),
                $ambangHari,
                number_format($total, 0, ',', '.'),
                $umurMaks
            ),
            rekomendasi: 'Minta rincian status penagihan tiap nomor bukti di bawah ini ke ADH cabang, dan pastikan berkasnya sudah dikirim ke pihak pembiayaan. Piutang tertua didahulukan.',
            nominal: $total,
            tanggal: (string) $tanggalSnapshot,
            detail: [
                'tanggal_snapshot' => (string) $tanggalSnapshot,
                'ambang_hari'      => $ambangHari,
                'items'            => $menunggak->map(fn(AnalisaAccReceivable $r) => [
                    'kode_konsumen'     => $r->kode_konsumen,
                    'no_bukti'          => $r->no_bukti,
                    'tanggal_transaksi' => $r->tanggal_transaksi->toDateString(),
                    // Dibulatkan ke int supaya yang tampil di layar "22 hari", bukan "22.0".
                    'umur_hari'         => (int) $r->tanggal_transaksi->diffInDays($r->tanggal_laporan),
                    'nominal'           => (float) $r->nominal,
                ])->all(),
            ],
        )];
    }
}
