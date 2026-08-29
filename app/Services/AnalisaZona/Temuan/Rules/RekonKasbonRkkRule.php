<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaPosisiKas;
use App\Models\AnalisaRkkTransaction;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;

/**
 * Rekonsiliasi kas kecil: pos "KAS - Penggantian untuk kasbon" (akun 22013)
 * di LHPBK harus sama dengan jumlah seluruh voucher RKK pada tanggal yang
 * sama — LHPBK mencatat berapa kas yang dikeluarkan untuk mengganti kasbon,
 * RKK merinci voucher-voucher yang diganti itu.
 *
 * Pada data nyata SOTDB 26 Agustus 2026 keduanya cocok sampai rupiah
 * terakhir (Rp 8.853.300, voucher 0115 s/d 0123 — rentang yang memang
 * disebut di keterangan LHPBK-nya). Karena hubungannya eksak seperti itu,
 * selisih berapa pun berarti ada voucher yang tidak tercatat di salah satu
 * sisi, dan itu wajib ditelusuri.
 */
class RekonKasbonRkkRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'rekon-kasbon-rkk';
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
            // Kalau satu tanggal punya lebih dari satu LHPBK (cabang mengirim
            // ulang versi koreksi), yang dipakai versi terakhir.
            ->keyBy(fn(AnalisaPosisiKas $p) => $p->tanggal->toDateString());

        foreach ($posisiKas as $tanggal => $lhpbk) {
            $kasbon = (float) $lhpbk->penggantian_kasbon;

            $rkk = AnalisaRkkTransaction::where('unit_usaha_code', $unitUsahaCode)
                ->whereDate('tanggal', $tanggal)
                ->get();

            // Tidak ada satupun sisi yang berisi -> memang tidak ada
            // penggantian kasbon hari itu, bukan temuan.
            if ($kasbon == 0.0 && $rkk->isEmpty()) {
                continue;
            }

            $totalRkk = (float) $rkk->sum('nominal');
            $selisih  = $totalRkk - $kasbon;

            if (abs($selisih) <= $toleransi) {
                continue;
            }

            $arah = $selisih > 0
                ? 'voucher RKK LEBIH BESAR daripada kas yang dikeluarkan'
                : 'kas yang dikeluarkan LEBIH BESAR daripada voucher RKK';

            $temuan[] = Temuan::tinggi(
                judul: sprintf(
                    'Selisih kas kecil %s: RKK Rp %s vs LHPBK Rp %s (beda Rp %s)',
                    $tanggal,
                    number_format($totalRkk, 0, ',', '.'),
                    number_format($kasbon, 0, ',', '.'),
                    number_format(abs($selisih), 0, ',', '.')
                ),
                rekomendasi: sprintf(
                    'Cocokkan fisik voucher kas kecil tanggal %s dengan LHPBK — %s. %s',
                    $tanggal,
                    $arah,
                    $lhpbk->penggantian_kasbon_ket
                        ? 'Keterangan LHPBK menyebut rentang voucher: ' . $lhpbk->penggantian_kasbon_ket
                        : 'Minta rincian nomor voucher yang diganti ke kasir cabang.'
                ),
                nominal: abs($selisih),
                tanggal: $tanggal,
                detail: [
                    'total_rkk'      => $totalRkk,
                    'kasbon_lhpbk'   => $kasbon,
                    'selisih'        => $selisih,
                    'jumlah_voucher' => $rkk->count(),
                    'no_voucher'     => $rkk->pluck('no_voucher')->filter()->unique()->sort()->values()->all(),
                    'keterangan_lhpbk' => $lhpbk->penggantian_kasbon_ket,
                ],
            );
        }

        return $temuan;
    }
}
