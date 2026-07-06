<?php

namespace App\Http\Controllers;

use App\Models\BpkbOnhandItem;
use App\Models\DbHargaSmh;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
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
use App\Models\SmhOnhandItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportPdfController extends Controller
{
    public function show(PlanAudit $plan): View
    {
        return view('akta.pdf.report-audit', $this->buildViewData($plan));
    }

    public function download(PlanAudit $plan): Response
    {
        $viewData = $this->buildViewData($plan);
        // For PDF download: replace PDF embeds with placeholder (DomPDF cannot render <embed>)
        foreach ($viewData['lampiranEmbeds'] as &$embed) {
            if ($embed['type'] === 'pdf') {
                $embed['data'] = null; // will show fallback in blade
            }
        }
        unset($embed);

        $pdf = Pdf::loadView('akta.pdf.report-audit', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true)
            ->setOption('defaultFont', 'Arial')
            ->setOption('dpi', 110);

        $filename = 'Laporan-Audit-' . ($plan->no_spt ? str_replace('/', '-', $plan->no_spt) : $plan->id) . '.pdf';

        return $pdf->download($filename);
    }

    private function buildViewData(PlanAudit $plan): array
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

        $lampiranEmbeds = [];
        if ($lampiran) {
            foreach ($lampiran->files_json ?? [] as $f) {
                $ext  = strtolower($f['ext'] ?? '');
                $path = $f['path'] ?? '';
                $absPath = storage_path('app/public/' . $path);
                $embed = ['file' => $f, 'type' => 'other', 'data' => null];
                if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $path && file_exists($absPath)) {
                    $mime = match($ext) { 'jpg','jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', default => 'image/webp' };
                    $embed['type'] = 'image';
                    $embed['data'] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absPath));
                } elseif ($ext === 'pdf' && $path && file_exists($absPath)) {
                    $embed['type'] = 'pdf';
                    $embed['data'] = 'data:application/pdf;base64,'.base64_encode(file_get_contents($absPath));
                }
                $lampiranEmbeds[] = $embed;
            }
        }

        $plafon = $this->buildPlafonAnalisa($plan);

        return compact(
            'plan', 'plafon', 'kas', 'smh', 'perlengkapan', 'bank', 'materai',
            'bpkbOnhand', 'bpkbInproses', 'kwitansi', 'piutangReguler',
            'piutangCdn', 'ttpGantung', 'cekFisik', 'mt', 'hgp', 'hga',
            'smhTarikan', 'lampiran', 'lampiranEmbeds'
        );
    }

    private function buildPlafonAnalisa(PlanAudit $plan): array
    {
        $cabang    = $plan->cabang ?? '';
        $cabangSfx = $this->suffix3($cabang);

        $items = SmhOnhandItem::query()
            ->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $plan->id))
            ->get();

        $hargaMap = DbHargaSmh::all()
            ->keyBy(fn($r) => strtoupper(trim($r->kode_model ?? '')));

        $plafonRow = null;
        if ($cabangSfx) {
            foreach (DbPlafon::all() as $p) {
                foreach ([$p->nama, $p->kode] as $key) {
                    if ($this->suffix3($key) === $cabangSfx) { $plafonRow = $p; break 2; }
                }
            }
        }
        $plafonNilai = $plafonRow ? (float) $plafonRow->nilai : null;

        $unitRow = null;
        if ($cabangSfx) {
            foreach (DbUnitUsaha::all() as $u) {
                if ($this->suffix3($u->unit_usaha) === $cabangSfx) { $unitRow = $u; break; }
            }
        }

        $grouped = [];
        foreach ($items as $item) {
            $gudang    = trim($item->gudang ?? '-');
            $kodeModel = strtoupper(trim($item->kode_model ?? ''));
            $hargaRow  = $hargaMap[$kodeModel] ?? null;
            $harga     = $hargaRow ? (float) $hargaRow->harga : null;

            if (!isset($grouped[$gudang])) {
                $grouped[$gudang] = ['gudang'=>$gudang,'totalUnit'=>0,'ditemukan'=>0,'tidakDitemukan'=>0,'totalNilai'=>0.0,'detail'=>[]];
            }
            $grouped[$gudang]['totalUnit']++;
            if ($harga !== null) { $grouped[$gudang]['ditemukan']++; $grouped[$gudang]['totalNilai'] += $harga; }
            else                 { $grouped[$gudang]['tidakDitemukan']++; }
            $grouped[$gudang]['detail'][] = [
                'noMesin'=>$item->no_mesin,'noRangka'=>$item->no_rangka,
                'kodeModel'=>$item->kode_model,'namaSmh'=>$hargaRow?->nama_smh,
                'harga'=>$harga,'gudang'=>$item->gudang,
            ];
        }

        $totalUnit  = array_sum(array_column(array_values($grouped), 'totalUnit'));
        $totalNilai = array_sum(array_column(array_values($grouped), 'totalNilai'));
        $sisaTotal  = $plafonNilai !== null ? max(0, $plafonNilai - $totalNilai) : null;
        $persen     = ($plafonNilai && $plafonNilai > 0) ? round($totalNilai / $plafonNilai * 100, 1) : null;

        return [
            'cabang'       => $cabang,
            'namaUnit'     => $unitRow?->unit_usaha ?? $cabang,
            'wilayah'      => $unitRow?->wilayah ?? '-',
            'plafonNilai'  => $plafonNilai,
            'plafonNama'   => $plafonRow?->nama ?? null,
            'totalUnit'    => $totalUnit,
            'totalNilai'   => $totalNilai,
            'sisaTotal'    => $sisaTotal,
            'persentase'   => $persen,
            'perUnit'      => array_values($grouped),
        ];
    }

    private function suffix3(?string $str): ?string
    {
        if (!$str) return null;
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $str));
        return strlen($clean) >= 3 ? substr($clean, -3) : null;
    }
}
