<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Penjaga terakhir untuk tool pemeriksaan yang menyimpan SELURUH isi layarnya
 * sekaligus (satu baris berisi satu dokumen JSON per plan audit).
 *
 * Satu SPT dikerjakan beberapa auditor. Kalau dua layar sama-sama mengirim
 * seluruh isinya, yang menyimpan belakangan menulis dari salinan yang belum
 * memuat pekerjaan rekannya — dan pekerjaan itu hilang tanpa jejak, tanpa
 * seorang pun tahu. Itu yang terjadi pada pemeriksaan MT: enam mekanik
 * diperiksa berdua, yang tersisa tiga.
 *
 * Beberapa tool sudah punya penjaga yang lebih pintar dan menggabungkan isinya
 * (HGP/RSA/HGA lewat MenjagaHasilPemeriksaan, MT lewat penggabungan per
 * mekanik). Sisanya bentuk datanya berbeda-beda dan tidak punya kunci yang
 * bisa dipakai menggabung dengan aman. Untuk mereka aturannya dibuat sederhana
 * tapi mutlak: SEBUAH KIRIMAN TIDAK PERNAH BOLEH MENIMPA VERSI YANG BELUM
 * PERNAH DILIHAT LAYAR PENGIRIMNYA.
 *
 * Layar mengirim "versi" — waktu perubahan terakhir yang ia terima saat memuat.
 * Kalau server sudah lebih baru, kiriman ditolak 409 dan auditor diminta memuat
 * ulang tabnya. Merepotkan sesaat, tapi tidak pernah menghilangkan pekerjaan
 * siapa pun.
 *
 * Kiriman tanpa "versi" (tab lama yang belum dimuat ulang setelah pembaruan
 * ini) tetap dilayani — memutus tab yang sedang dipakai auditor di lapangan
 * lebih merugikan daripada risiko yang tersisa sampai tabnya dimuat ulang.
 */
trait MenolakTimpaanBasi
{
    protected function tolakKalauBasi(?Model $rec, Request $request, string $namaTab): void
    {
        // Belum ada isinya di server: tidak ada yang bisa tertimpa.
        if (! $rec) {
            return;
        }
        if (! $request->has('versi')) {
            return;
        }

        $diLayar  = $this->saatPerubahan($request->input('versi'));
        $diServer = $this->saatPerubahan($rec->updated_at);

        if ($diLayar === $diServer) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => "Data {$namaTab} di server sudah lebih baru dari yang ada di layar ini. "
                . 'Kalau ditimpa, hasil kerja rekan auditor bisa hilang — muat ulang tab ini dulu, '
                . 'lalu ulangi perubahan Anda.',
            'stale'       => true,
            'versiServer' => (string) ($rec->updated_at?->toDateTimeString() ?? ''),
        ], 409));
    }

    /**
     * Dua sisi perbandingan disamakan dulu ke satu bentuk, bukan diadu sebagai
     * teks mentah: tab yang berbeda menerima waktu perubahan dalam bentuk yang
     * berbeda pula. Sebagian besar tab memakai toAktaArray() yang menuliskannya
     * sebagai "2026-09-18 03:12:45", sementara Pemeriksaan Kas mengembalikan
     * model apa adanya sehingga peramban menerima "2026-09-18T03:12:45.000000Z".
     * Diadu sebagai teks, keduanya selalu berbeda — dan tab Kas menolak SETIAP
     * simpanan kedua dengan peringatan "server sudah lebih baru", padahal
     * layarnya baru saja memuat data itu sendiri dan tidak ada rekan auditor
     * mana pun yang menyimpan di sela-selanya.
     */
    private function saatPerubahan(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }
        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->utc()->format('Y-m-d H:i:s');
        }

        $teks = trim((string) $nilai);
        if ($teks === '') {
            return null;
        }

        try {
            return Carbon::parse($teks)->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $teks;   // bentuk yang tidak dikenali dibandingkan apa adanya
        }
    }
}
