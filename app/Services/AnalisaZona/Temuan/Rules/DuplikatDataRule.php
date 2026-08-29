<?php

namespace App\Services\AnalisaZona\Temuan\Rules;

use App\Models\AnalisaAccConsumer;
use App\Models\AnalisaAccReceivable;
use App\Services\AnalisaZona\Temuan\Temuan;
use App\Services\AnalisaZona\Temuan\TemuanRuleInterface;
use Illuminate\Support\Facades\DB;

/**
 * Baris kembar DI DALAM SATU file/hari yang sama — indikasi input ganda di
 * sistem sumber.
 *
 * PENTING: pengecekan dibatasi per upload (per file/hari), BUKAN lintas hari.
 * Piutang dan konsumen yang sama WAJAR muncul berulang di file hari-hari
 * berikutnya (file .ACC memuat ulang seluruh data yang belum selesai setiap
 * hari) — menganggap itu duplikat akan membanjiri auditor dengan temuan
 * palsu. Yang benar-benar janggal adalah baris kembar dalam satu file.
 */
class DuplikatDataRule implements TemuanRuleInterface
{
    public function kode(): string
    {
        return 'duplikat-data';
    }

    public function evaluate(string $unitUsahaCode, string $start, string $end): array
    {
        $dupPiutang = AnalisaAccReceivable::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal_laporan', [$start, $end])
            ->select('upload_id', 'tanggal_laporan', 'kode_konsumen', 'no_bukti', DB::raw('COUNT(*) as jml'))
            ->groupBy('upload_id', 'tanggal_laporan', 'kode_konsumen', 'no_bukti')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $dupKonsumen = AnalisaAccConsumer::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('nik')
            ->where('nik', '!=', '')
            ->select('upload_id', 'tanggal', 'nik', DB::raw('COUNT(*) as jml'))
            ->groupBy('upload_id', 'tanggal', 'nik')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dupPiutang->isEmpty() && $dupKonsumen->isEmpty()) {
            return [];
        }

        // Yang dihitung adalah baris BERLEBIH (jml - 1), bukan jumlah grup —
        // 1 baris muncul 3x berarti 2 baris berlebih.
        $jumlahBerlebih = $dupPiutang->sum(fn($r) => $r->jml - 1) + $dupKonsumen->sum(fn($r) => $r->jml - 1);

        return [Temuan::rendah(
            judul: sprintf(
                '%d baris kembar dalam file yang sama (%d piutang, %d konsumen)',
                $jumlahBerlebih,
                $dupPiutang->sum(fn($r) => $r->jml - 1),
                $dupKonsumen->sum(fn($r) => $r->jml - 1)
            ),
            rekomendasi: 'Sampaikan ke cabang untuk mengecek input ganda di sistem sumber. Pastikan nominalnya tidak ikut terhitung dua kali di laporan mereka.',
            detail: [
                'piutang' => $dupPiutang->map(fn($r) => [
                    'tanggal'       => (string) $r->tanggal_laporan,
                    'kode_konsumen' => $r->kode_konsumen,
                    'no_bukti'      => $r->no_bukti,
                    'jumlah_baris'  => (int) $r->jml,
                ])->all(),
                // NIK sengaja TIDAK ikut ditampilkan di sini — untuk keperluan
                // ini cukup diketahui ada berapa dan di tanggal berapa;
                // identitas konsumennya bisa dilihat lewat drill-down yang
                // sudah punya penyamaran NIK/HP sendiri.
                'konsumen' => $dupKonsumen->map(fn($r) => [
                    'tanggal'      => (string) $r->tanggal,
                    'jumlah_baris' => (int) $r->jml,
                ])->all(),
            ],
        )];
    }
}
