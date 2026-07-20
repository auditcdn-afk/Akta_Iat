<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\SmhOnhandItem;
use App\Models\DbPerlengkapan;
use App\Models\PlanAudit;
use App\Models\DbUnitUsaha;

// PemeriksaanPerlengkapanController::smhSummary() sebelumnya menghitung "Saldo
// (buku)" tiap jenis perlengkapan pakai TOTAL SELURUH unit onhand plan (semua
// tipe motor digabung jadi satu angka) — padahal tiap jenis perlengkapan (mis.
// "Kaca Spion PCX160") cuma relevan untuk tipe motor tertentu sesuai
// db_perlengkapan.keterangan. Baris pemeriksaan_perlengkapan yang sudah
// terlanjur disimpan sebelum endpoint-nya diperbaiki masih menyimpan
// saldo/selisih yang salah tersebut. Migrasi ini menghitung ulang saldo &
// selisih setiap baris yang sudah ada memakai logika yang sama dengan
// endpoint yang sudah diperbaiki, supaya data lama ikut benar tanpa auditor
// harus buka & simpan ulang satu-satu.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pemeriksaan_perlengkapan')) {
            return;
        }

        $rows = PemeriksaanPerlengkapan::all();
        if ($rows->isEmpty()) {
            return;
        }

        $dbPerlengkapanByKode = DbPerlengkapan::all()->groupBy('kode');

        // Cache per plan_audit_id supaya tidak query onhand berulang untuk tiap baris.
        $totalOnhandCache = [];

        foreach ($rows as $row) {
            $planId = $row->plan_audit_id;
            $nama   = trim($row->jenis_perlengkapan ?? '');
            if ($nama === '') continue;

            if (!array_key_exists($planId, $totalOnhandCache)) {
                $totalOnhandCache[$planId] = $this->computeTotalOnhandPerJenis($planId, $dbPerlengkapanByKode);
            }

            $newSaldo   = (float) ($totalOnhandCache[$planId][$nama] ?? 0);
            $newSelisih = (float) $row->fisik - $newSaldo;

            $row->saldo   = $newSaldo;
            $row->selisih = $newSelisih;
            $row->save();
        }
    }

    public function down(): void
    {
        // Tidak ada rollback bermakna: nilai lama yang salah tidak disimpan
        // sebelum migrasi ini jalan, jadi tidak ada apa pun untuk dikembalikan.
    }

    private function computeTotalOnhandPerJenis(int $planId, $dbPerlengkapanByKode): array
    {
        $wilayah = $this->wilayahFromPlan($planId);

        $kodeItemMap = [];
        foreach ($dbPerlengkapanByKode as $kode => $rows) {
            $match = $rows->first(fn($r) => $wilayah && strtolower(trim($r->wilayah ?? '')) === $wilayah)
                ?? $rows->first(fn($r) => empty($r->wilayah))
                ?? $rows->first();
            $kodeItemMap[$kode] = $match?->itemList() ?? [];
        }

        $onhand = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId))
            ->get(['no_mesin']);

        $totalPerJenis = [];
        foreach ($onhand as $u) {
            $prefix = strtoupper(substr(str_replace(' ', '', $u->no_mesin ?? ''), 0, 5));
            foreach ($kodeItemMap[$prefix] ?? [] as $nm) {
                $totalPerJenis[$nm] = ($totalPerJenis[$nm] ?? 0) + 1;
            }
        }

        return $totalPerJenis;
    }

    private function wilayahFromPlan(?int $planId): ?string
    {
        if (!$planId) return null;
        $plan = PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->wilayah ?? '')) : null;
    }
};
