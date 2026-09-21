<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Penyegaran berkala tab pemeriksaan, tanpa mengirim ulang seluruh daftar.
 *
 * Tiap layar auditor menarik keadaan terbaru tiap 20 detik supaya hasil scan
 * rekan ikut terlihat. Dulu yang dikirim SELURUH items_json -- pada audit
 * gudang WHS isinya 4.781 item (±1,3 MB). Dengan lima auditor, itu berarti
 * lima kali membaca, menguraikan, menyusun ulang, dan mengirim 1,3 MB setiap
 * 20 detik, walau tidak ada satu pun scan baru di antaranya.
 *
 * Yang dikirim sekarang:
 *   - tidak ada perubahan sama sekali  -> beberapa puluh byte, items_json
 *     bahkan tidak dibaca dari database;
 *   - ada perubahan -> hanya item yang MEMANG sudah disentuh pemeriksaan.
 *     Item yang belum discan siapa pun tidak perlu dikirim: isinya sama persis
 *     dengan yang sudah ada di layar sejak import.
 *
 * Daftar itemnya sendiri (No. Part & saldo akhir) bisa berubah kalau ada yang
 * mengimpor ulang atau menambah part manual. Itu ditandai lewat "sidik" --
 * layar yang sidiknya tidak lagi cocok memuat ulang penuh sekali.
 */
trait PenyegaranRingkas
{
    /**
     * Bentuk jawaban endpoint show untuk satu tool pemeriksaan.
     *
     * @param  class-string<Model>  $model
     */
    protected function jawabanPemeriksaan(string $model, Request $request): array
    {
        $planId = $request->query('plan_audit_id');

        if (!$request->boolean('ringkas')) {
            $rec = $model::query()->where('plan_audit_id', $planId)->first();

            return ['data' => $rec ? $this->dataDenganSidik($rec) : null];
        }

        // Jalur cepat: versi dibaca tanpa mengambil items_json sama sekali.
        $versi = $this->versiPemeriksaan($model, $planId);

        if ($versi === null) {
            return ['ringkas' => true, 'berubah' => false, 'versi' => null];
        }

        if ($request->query('versi') === $versi) {
            return ['ringkas' => true, 'berubah' => false, 'versi' => $versi];
        }

        $rec   = $model::query()->where('plan_audit_id', $planId)->first();
        $items = $rec?->items_json ?? [];

        return [
            'ringkas' => true,
            'berubah' => true,
            'versi'   => $versi,
            'sidik'   => $this->sidikDaftar($items),
            'data'    => ['items' => $this->itemYangSudahDisentuh($items)],
        ];
    }

    /**
     * Penanda versi baris pemeriksaan, dibaca TANPA mengambil isi items_json:
     * waktu perubahan + panjang isinya (dihitung di database, tidak dikirim).
     *
     * Waktu perubahan saja tidak cukup. Kolomnya hanya berketelitian detik,
     * sementara lima auditor bisa menyimpan beberapa kali dalam detik yang
     * sama -- penyegaran berikutnya akan mengira tidak ada yang berubah dan
     * hasil scan rekan tidak pernah sampai ke layar lain. Panjang isi ikut
     * menutup itu: satu scan selalu menambah entri di logScan, jadi panjangnya
     * pasti berbeda.
     */
    protected function versiPemeriksaan(string $model, mixed $planId): ?string
    {
        $baris = $model::query()
            ->where('plan_audit_id', $planId)
            ->selectRaw('updated_at, LENGTH(items_json) as panjang_isi')
            ->first();

        if (!$baris || !$baris->updated_at) {
            return null;
        }

        return $baris->updated_at->toIso8601String() . '|' . (int) $baris->panjang_isi;
    }

    /** Payload penuh + sidik daftarnya, supaya layar tahu daftar apa yang dipegangnya. */
    protected function dataDenganSidik(Model $rec): array
    {
        return $rec->toAktaArray() + ['sidik' => $this->sidikDaftar($rec->items_json ?? [])];
    }

    /**
     * Sidik daftar item: berubah kalau ada part yang bertambah, hilang, atau
     * saldo akhirnya diganti oleh import ulang.
     */
    protected function sidikDaftar(array $items): string
    {
        $bahan = '';

        foreach ($items as $it) {
            if (!is_array($it)) continue;

            $bahan .= ($it['noPart'] ?? '') . '|' . ($it['saldoAkhir'] ?? $it['saldoAwal'] ?? '') . "\n";
        }

        return md5($bahan);
    }

    /**
     * Item yang sudah ada jejak pemeriksaannya -- itulah yang mungkin berbeda
     * dari isi layar rekan.
     *
     * Yang tidak tertangkap: baris yang HANYA tanggal periksanya diubah tanpa
     * scan, WO, atau keterangan. Itu catatan milik auditor yang mengubahnya dan
     * tidak memengaruhi hitungan siapa pun; layar rekan menyusul saat tabnya
     * dimuat ulang.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function itemYangSudahDisentuh(array $items): array
    {
        $disentuh = [];

        foreach ($items as $it) {
            if (!is_array($it)) continue;

            $angka = fn($v) => (float) (is_numeric($v) ? $v : 0);
            $isi   = fn($v) => trim((string) ($v ?? '')) !== '';

            $adaJejak = $angka($it['fisik'] ?? 0) != 0.0
                || $angka($it['wo'] ?? 0) != 0.0
                || $angka($it['fisikTtp'] ?? 0) != 0.0
                || (is_array($it['logScan'] ?? null) && $it['logScan'] !== [])
                || $isi($it['keterangan'] ?? null)
                || $isi($it['keteranganTtp'] ?? null);

            if ($adaJejak) {
                $disentuh[] = $it;
            }
        }

        return $disentuh;
    }
}
