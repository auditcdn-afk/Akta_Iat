<?php

namespace App\Services;

use App\Models\AuditGrading;
use App\Models\AuditRecommendation;
use App\Models\Pica;
use App\Models\PlanAudit;
use App\Models\RealisasiDinas;
use App\Models\ReportAuditChecklistItem;
use App\Models\ReportAuditSummary;
use App\Models\SuratKeputusan;

// Membangun ulang report_audit_summaries + report_audit_checklist_items dari
// tabel transaksional (plan_audits, realisasi_dinas, audit_gradings,
// surat_keputusan, audit_recommendations, picas).
//
// Dipanggil secara terjadwal lewat Artisan command `report-audit:refresh`
// (lihat routes/console.php), BUKAN saat request biasa — supaya perhitungan
// join + parsing JSON yang lumayan berat ini tidak membebani aplikasi yang
// dipakai auditor sehari-hari. Power BI dan fitur export Excel cukup baca
// hasil akhirnya.
class ReportAuditFlattener
{
    public function refreshAll(): int
    {
        $count = 0;

        PlanAudit::query()->chunkById(200, function ($plans) use (&$count) {
            foreach ($plans as $plan) {
                $this->refreshPlan($plan);
                $count++;
            }
        });

        return $count;
    }

    public function refreshPlan(PlanAudit $plan): void
    {
        $now = now();

        $realisasi = RealisasiDinas::where('plan_audit_id', $plan->id)->with('items')->first();
        $biaya = $this->summarizeBiaya($realisasi);

        $grading = AuditGrading::where('plan_audit_id', $plan->id)->first();

        $sk = SuratKeputusan::where('plan_audit_id', $plan->id)->latest('id')->first();

        $rekomendasi = AuditRecommendation::where('plan_audit_id', $plan->id)->get();
        $picas = Pica::where('plan_audit_id', $plan->id)->get();

        $jumlahHari = ($plan->tgl_mulai && $plan->tgl_selesai)
            ? $plan->tgl_mulai->diffInDays($plan->tgl_selesai) + 1
            : null;

        ReportAuditSummary::updateOrCreate(
            ['plan_audit_id' => $plan->id],
            [
                'no_spt' => $plan->no_spt,
                'unit_usaha' => $plan->cabang,
                'cabang_area' => $plan->cabang_area,
                'jenis_audit' => $plan->jenis_audit,
                'kepala_tim' => $plan->kepala_tim,
                'anggota_tim' => implode(', ', $plan->tim ?? []),
                'status_plan' => $plan->status,

                'tgl_plan' => $plan->tgl_plan,
                'tgl_mulai' => $plan->tgl_mulai,
                'tgl_selesai' => $plan->tgl_selesai,
                'jumlah_hari' => $jumlahHari,

                ...$biaya,

                'fraud' => $grading?->fraud,
                'jenis_fraud' => implode(', ', $grading?->jenis_fraud ?? []),
                'keterangan_fraud' => $grading?->keterangan_fraud,

                'nilai_grading' => $grading?->total_nilai,
                'jumlah_item_grading' => count($grading?->details ?? []),

                'no_sk' => $sk?->no_sk,
                'status_sk' => $sk?->status,
                'tgl_sk_dibuat' => $sk?->created_at?->toDateString(),
                'tgl_sk_selesai' => $sk?->status === 'selesai' ? $sk?->updated_at?->toDateString() : null,

                'jumlah_rekomendasi' => $rekomendasi->count(),
                'rekomendasi_selesai' => $rekomendasi->whereIn('status', ['done', 'approved'])->count(),
                'ringkasan_rekomendasi' => $rekomendasi->pluck('deskripsi')->filter()->implode(' | '),

                'jumlah_pica' => $picas->count(),
                'pica_closed' => $picas->where('status', 'closed')->count(),
                'ringkasan_pica' => $picas->pluck('problem')->filter()->implode(' | '),

                'refreshed_at' => $now,
            ]
        );

        ReportAuditChecklistItem::where('plan_audit_id', $plan->id)->delete();

        if ($grading && !empty($grading->details)) {
            $rows = [];
            foreach ($grading->details as $idx => $item) {
                $rows[] = [
                    'plan_audit_id' => $plan->id,
                    'audit_grading_id' => $grading->id,
                    'jenis' => $grading->jenis,
                    'urutan' => $idx,
                    'nama_pemeriksaan' => $item['namaPemeriksaan'] ?? null,
                    'current_condition' => $item['currentCondition'] ?? null,
                    'nilai' => is_numeric($item['nilai'] ?? null) ? $item['nilai'] : null,
                    'refreshed_at' => $now,
                ];
            }
            if ($rows) {
                ReportAuditChecklistItem::insert($rows);
            }
        }
    }

    /** @return array<string,float> */
    private function summarizeBiaya(?RealisasiDinas $realisasi): array
    {
        $map = [
            'Akomodasi' => 'biaya_akomodasi',
            'Transportasi Darat' => 'biaya_transportasi_darat',
            'Transportasi Laut' => 'biaya_transportasi_laut',
            'Transportasi Udara' => 'biaya_transportasi_udara',
            'Konsumsi' => 'biaya_konsumsi',
            'Laundry' => 'biaya_laundry',
            'Pramenu' => 'biaya_pramenu',
            'Biaya Perobatan' => 'biaya_perobatan',
            'Komunikasi' => 'biaya_komunikasi',
            'Lain-lain' => 'biaya_lain_lain',
        ];

        $result = array_fill_keys(array_values($map), 0.0);

        $total = 0.0;
        foreach ($realisasi?->items ?? [] as $item) {
            $col = $map[$item->jenis_pengeluaran] ?? null;
            if ($col) {
                $result[$col] += (float) $item->nominal;
            }
            $total += (float) $item->nominal;
        }
        $result['biaya_total'] = $total;

        return $result;
    }
}
