<?php

namespace App\Services;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;

/**
 * Satu sumber kebenaran untuk pertanyaan "berapa unit onhand yang membutuhkan
 * jenis perlengkapan ini, dan berapa yang sudah ditemukan saat cek fisik?".
 *
 * Angka ini dipakai dua tempat yang harus saling cocok:
 *
 *  1. Tab Perlengkapan (PemeriksaanPerlengkapanController) — Saldo buku
 *     "Perlengkapan di luar SMH" = unit yang membutuhkan dikurangi unit yang
 *     perlengkapannya sudah ditemukan.
 *  2. Report Audit bagian "C. REKAP GABUNGAN PERLENGKAPAN PER JENIS" — kolom
 *     "SMH Cek Fisik".
 *
 * Sebelumnya keduanya menghitung sendiri-sendiri dengan penyebut berbeda:
 * report hanya menghitung unit yang status fisiknya 'ada' DAN checklist
 * perlengkapannya sudah tersinkron, sedangkan Saldo Luar SMH menghitung
 * SELURUH unit onhand. Akibatnya kedua sisi tabel tidak pernah bisa
 * direkonsiliasi — mis. 3 unit dengan 1 unit tidak ditemukan: report bilang
 * kurang 1, Saldo Luar SMH bilang 2.
 *
 * Penyebut yang benar adalah seluruh unit onhand yang membutuhkan perlengkapan
 * itu. Unit yang tidak ditemukan fisik, atau yang checklist-nya belum diisi,
 * tetap membutuhkan perlengkapannya — kekurangannya tidak boleh hilang dari
 * laporan hanya karena unitnya belum/tidak sempat diperiksa.
 */
class PerlengkapanOnhand
{
    /** @var array<string, array<string, array>> cache per plan dalam satu request */
    private array $summaryCache = [];

    /**
     * Jumlah unit onhand yang membutuhkan tiap jenis perlengkapan.
     *
     * Tiap jenis perlengkapan (mis. "Kaca Spion PCX160") hanya relevan untuk tipe
     * motor tertentu, jadi hitungannya bukan total seluruh unit onhand digabung.
     * Tipe motor diambil dari 5 huruf pertama no mesin dan dicocokkan ke
     * db_perlengkapan.kode — konvensi yang sama dipakai
     * PemeriksaanSmhController::syncPerlengkapan().
     *
     * @return array<string, int> nama perlengkapan => jumlah unit
     */
    public function expectedPerJenis(?string $planId): array
    {
        $kodeItemMap = $this->kodeItemMap($this->wilayahFromPlan($planId));

        $onhandQuery = SmhOnhandItem::query();

        if ($planId) {
            $onhandQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        $expected = [];

        foreach ($onhandQuery->get(['no_mesin']) as $unit) {
            $kode = strtoupper(substr(str_replace(' ', '', $unit->no_mesin ?? ''), 0, 5));

            foreach ($kodeItemMap[$kode] ?? [] as $nama) {
                $expected[$nama] = ($expected[$nama] ?? 0) + 1;
            }
        }

        return $expected;
    }

    /**
     * Rekap per jenis perlengkapan untuk satu plan.
     *
     * Selalu diawali dari SELURUH data onhand, baru ditimpa hasil cek fisik.
     * Kalau dibalik — mulai dari perlengkapan_json lalu jatuh ke onhand — maka
     * sebelum ada unit yang diperiksa hasilnya array kosong dan form
     * "Perlengkapan di luar SMH" menampilkan Saldo 0 untuk semua jenis, seolah
     * data onhand tidak tersinkron dengan db_perlengkapan.
     *
     * @return array<string, array{nama: string, ada: int, total: int, totalOnhand: int}>
     */
    public function summaryPerJenis(?string $planId): array
    {
        $key = (string) $planId;

        if (isset($this->summaryCache[$key])) {
            return $this->summaryCache[$key];
        }

        $totalOnhandPerJenis = $this->expectedPerJenis($planId);

        $summary = [];
        foreach ($totalOnhandPerJenis as $nama => $totalOnhand) {
            $summary[$nama] = [
                'nama'        => $nama,
                'ada'         => 0,
                'total'       => 0,
                'totalOnhand' => $totalOnhand,
            ];
        }

        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json')
            ->where('status_fisik', 'ada');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        // Timpa dengan hasil pemeriksaan fisik: ada vs total di setiap unit.
        foreach ($itemsQuery->get() as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama === '') continue;

                $summary[$nama] ??= [
                    'nama'        => $nama,
                    'ada'         => 0,
                    'total'       => 0,
                    'totalOnhand' => $totalOnhandPerJenis[$nama] ?? 0,
                ];

                $summary[$nama]['total']++;
                if ($pl['ada'] ?? false) $summary[$nama]['ada']++;
            }
        }

        // Nama yang hanya muncul di checklist manual auditor (tidak ada di
        // db_perlengkapan) tidak punya angka onhand. Supaya penyebutnya tidak nol
        // — yang membuat seluruh temuannya seolah kelebihan — dipakai jumlah unit
        // yang benar-benar diperiksa untuk jenis itu.
        foreach ($summary as $nama => $row) {
            if ($row['totalOnhand'] === 0) {
                $summary[$nama]['totalOnhand'] = $row['total'];
            }
        }

        return $this->summaryCache[$key] = $summary;
    }

    /**
     * Saldo buku satu jenis perlengkapan = unit yang membutuhkannya dikurangi
     * unit yang perlengkapannya sudah ditemukan saat periksa fisik.
     */
    public function saldoFor(?string $planId, string $jenis): float
    {
        $row = $this->summaryPerJenis($planId)[trim($jenis)] ?? null;

        if (!$row) {
            return 0.0;
        }

        return (float) max(0, $row['totalOnhand'] - $row['ada']);
    }

    /**
     * Peta kode tipe motor => daftar nama perlengkapan yang wajib ada.
     *
     * Satu kode bisa punya beberapa baris (per wilayah); dipilih yang cocok
     * wilayahnya, lalu baris tanpa wilayah, lalu apa pun yang ada.
     *
     * @return array<string, array<int, string>>
     */
    private function kodeItemMap(?string $wilayah): array
    {
        $map = [];

        foreach (DbPerlengkapan::all()->groupBy('kode') as $kode => $rows) {
            $match = $rows->first(fn($r) => $wilayah && strtolower(trim($r->wilayah ?? '')) === $wilayah)
                ?? $rows->first(fn($r) => blank($r->wilayah))
                ?? $rows->first();

            $map[$kode] = $match?->itemList() ?? [];
        }

        return $map;
    }

    /** Wilayah unit usaha milik plan, huruf kecil — dipakai memilih baris db_perlengkapan. */
    public function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;

        $plan = PlanAudit::find($planId);
        if (!$plan?->cabang) return null;

        $uu = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();

        return $uu ? strtolower(trim($uu->wilayah ?? '')) : null;
    }
}
