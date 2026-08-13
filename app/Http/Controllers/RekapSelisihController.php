<?php

namespace App\Http\Controllers;

use App\Models\DbAhmOil;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use Illuminate\View\View;

/**
 * "Rekap Selisih Part & AHM Oil's" — cetakan terpisah dari Report Audit besar,
 * khusus untuk tool HGP & AHM Oils / RSA HGP & AHM Oils. Hanya menampilkan
 * item yang selisihnya TIDAK nol, dipecah ke dua tabel:
 *   - AHM OIL'S  : kode part-nya terdaftar di database AHM Oil (db_ahm_oils)
 *   - SPAREPART  : sisanya
 *
 * Nomor baris ("NO") sengaja tetap memakai posisi item itu di daftar LENGKAP
 * (sebelum difilter selisih != 0), bukan dinomori ulang 1..n dari hasil
 * filter — supaya auditor bisa menelusuri balik ke baris yang sama di tabel
 * onhand penuh pada tab HGP/RSA HGP.
 */
class RekapSelisihController extends Controller
{
    private const TOOLS = ['hgp', 'rsa-hgp'];

    public function show(PlanAudit $plan, string $tool): View
    {
        abort_unless(in_array($tool, self::TOOLS, true), 404);

        $items = $tool === 'hgp'
            ? (PemeriksaanHgp::where('plan_audit_id', $plan->id)->first()?->items_json ?? [])
            : (PemeriksaanRsaHgp::where('plan_audit_id', $plan->id)->first()?->items_json ?? []);

        $kodeOli = DbAhmOil::query()->pluck('kode')
            ->map(fn($k) => strtolower(trim((string) $k)))
            ->filter()
            ->flip();

        $oilItems = [];
        $sparepartItems = [];

        foreach (array_values($items) as $i => $it) {
            $selisih = (float) ($it['selisih'] ?? 0);
            if ($selisih === 0.0) {
                continue;
            }

            $row = [...$it, 'no' => $i + 1, 'selisih' => $selisih];
            $kode = strtolower(trim((string) ($it['noPart'] ?? '')));

            if ($kode !== '' && $kodeOli->has($kode)) {
                $oilItems[] = $row;
            } else {
                $sparepartItems[] = $row;
            }
        }

        $auditor = PemeriksaanAuditor::where('plan_audit_id', $plan->id)->where('tool', $tool)->first();

        return view('akta.pdf.rekap-selisih', [
            'plan'           => $plan,
            'tool'           => $tool,
            'toolLabel'      => config("audit_tabs.{$tool}"),
            'oilItems'       => $oilItems,
            'sparepartItems' => $sparepartItems,
            'auditor'        => $auditor,
        ]);
    }
}
