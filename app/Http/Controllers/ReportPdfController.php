<?php

namespace App\Http\Controllers;

use App\Models\BpkbOnhandItem;
use App\Models\PemeriksaanBank;
use App\Models\PemeriksaanBpkbInproses;
use App\Models\PemeriksaanCekFisik;
use App\Models\PemeriksaanHga;
use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanKas;
use App\Models\PemeriksaanKwitansi;
use App\Models\PemeriksaanLampiran;
use App\Models\PemeriksaanMaterai;
use App\Models\PemeriksaanMt;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanPiutangCdn;
use App\Models\PemeriksaanPiutangReguler;
use App\Models\PemeriksaanSmh;
use App\Models\PemeriksaanSmhTarikan;
use App\Models\PemeriksaanTtpGantung;
use App\Models\PlanAudit;
use Illuminate\View\View;

class ReportPdfController extends Controller
{
    public function show(PlanAudit $plan): View
    {
        $id = $plan->id;

        $kas        = PemeriksaanKas::where('plan_audit_id', $id)->get();
        $smh        = PemeriksaanSmh::with('items')->where('plan_audit_id', $id)->get();
        $perlengkapan = PemeriksaanPerlengkapan::where('plan_audit_id', $id)->get();
        $bank       = PemeriksaanBank::where('plan_audit_id', $id)->get();
        $materai    = PemeriksaanMaterai::where('plan_audit_id', $id)->get();
        $bpkbOnhand = BpkbOnhandItem::where('plan_audit_id', $id)->get();
        $bpkbInproses = PemeriksaanBpkbInproses::where('plan_audit_id', $id)->get();
        $kwitansi   = PemeriksaanKwitansi::where('plan_audit_id', $id)->first();
        $piutangReguler = PemeriksaanPiutangReguler::where('plan_audit_id', $id)->first();
        $piutangCdn = PemeriksaanPiutangCdn::where('plan_audit_id', $id)->first();
        $ttpGantung = PemeriksaanTtpGantung::where('plan_audit_id', $id)->first();
        $cekFisik   = PemeriksaanCekFisik::where('plan_audit_id', $id)->first();
        $mt         = PemeriksaanMt::where('plan_audit_id', $id)->first();
        $hgp        = PemeriksaanHgp::where('plan_audit_id', $id)->first();
        $hga        = PemeriksaanHga::where('plan_audit_id', $id)->first();
        $smhTarikan = PemeriksaanSmhTarikan::where('plan_audit_id', $id)->first();
        $lampiran   = PemeriksaanLampiran::where('plan_audit_id', $id)->first();

        return view('akta.pdf.report-audit', compact(
            'plan', 'kas', 'smh', 'perlengkapan', 'bank', 'materai',
            'bpkbOnhand', 'bpkbInproses', 'kwitansi', 'piutangReguler',
            'piutangCdn', 'ttpGantung', 'cekFisik', 'mt', 'hgp', 'hga',
            'smhTarikan', 'lampiran'
        ));
    }
}
