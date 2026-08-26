<?php

namespace App\Http\Controllers;

use App\Models\AuditTabConfig;
use App\Models\BpkbOnhandItem;
use App\Models\DbAhmOil;
use App\Models\DbHargaSmh;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Models\Karyawan;
use App\Models\PlanAuditMandiri;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanBlanko;
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
use App\Models\PemeriksaanMutasiPembelian;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanPiutangCdn;
use App\Models\PemeriksaanPiutangReguler;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PemeriksaanSmh;
use App\Models\PemeriksaanSmhTarikan;
use App\Models\PemeriksaanTtpCsc;
use App\Models\PemeriksaanTtpGantung;
use App\Models\PlanAudit;
use App\Services\PerlengkapanOnhand;
use App\Models\SmhOnhandItem;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportPdfController extends Controller
{
    public function show(PlanAudit $plan): View
    {
        return view('akta.pdf.report-audit', $this->buildViewData($plan));
    }

    public function download(PlanAudit $plan): \Illuminate\View\View
    {
        $viewData = $this->buildViewData($plan);
        $viewData['autoprint'] = true;
        return view('akta.pdf.report-audit', $viewData);
    }

    private function buildViewData(PlanAudit $plan): array
    {
        $id = $plan->id;

        // Satu plan hanya boleh punya satu pemeriksaan kas (lihat updateOrCreate
        // di PemeriksaanKasController); ->latest()->first() sebagai jaga-jaga
        // kalau ada data lama sebelum unique constraint ditambahkan.
        $kas        = PemeriksaanKas::where('plan_audit_id', $id)->latest('id')->first();
        $smh        = PemeriksaanSmh::with('items')->where('plan_audit_id', $id)->get();
        $perlengkapan = PemeriksaanPerlengkapan::where('plan_audit_id', $id)->get();

        // Jumlah unit onhand yang MEMBUTUHKAN tiap jenis perlengkapan, dari sumber
        // yang sama dengan Saldo buku "Perlengkapan di luar SMH" di tab Perlengkapan.
        // Bagian C (Rekap Gabungan) dulu menghitung penyebutnya sendiri — hanya unit
        // yang status fisiknya 'ada' dan checklist-nya sudah tersinkron — sehingga
        // kedua sisi tabel tidak pernah bisa direkonsiliasi.
        $perlengkapanOnhand = app(PerlengkapanOnhand::class)->summaryPerJenis((string) $id);
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
        $rsaHgp     = PemeriksaanRsaHgp::where('plan_audit_id', $id)->first();
        $hga        = PemeriksaanHga::where('plan_audit_id', $id)->first();
        $smhTarikan = PemeriksaanSmhTarikan::where('plan_audit_id', $id)->first();
        $lampiran   = PemeriksaanLampiran::where('plan_audit_id', $id)->first();
        $mutasiPembelian = PemeriksaanMutasiPembelian::where('plan_audit_id', $id)->first();
        $ttpCsc     = PemeriksaanTtpCsc::where('plan_audit_id', $id)->first();

        // Nama Auditor & Auditee tiap tool — satu query, dikelompokkan per tool
        // key (config('audit_tabs')) supaya tiap section blade tinggal ambil
        // $auditors['kas'] dsb, alih-alih 19 query terpisah.
        $auditors = PemeriksaanAuditor::where('plan_audit_id', $id)->get()->keyBy('tool');

        // Register Blanko (H1/H2) untuk SMH & Onhand BPKB — tabel baru, jadi
        // dibungkus try/catch seperti $karyawans di bawah (migrasi manual
        // belum tentu sudah dijalankan setelah deploy).
        try {
            $blankos = PemeriksaanBlanko::where('plan_audit_id', $id)->get()->keyBy('tool');
        } catch (\Throwable $e) {
            $blankos = collect();
        }

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

        // Rekap Selisih Part & AHM Oil's — bagian dari section HGP/RSA HGP di
        // bawah, bukan cetakan terpisah. Kode part dicocokkan ke database AHM
        // Oil (dikelola lewat Database -> Database AHM Oil): cocok masuk
        // rekap AHM OIL'S, tidak cocok masuk SPAREPART. Hanya item yang
        // selisihnya tidak nol yang ditampilkan.
        $kodeOli = DbAhmOil::query()->pluck('kode')
            ->map(fn($k) => strtolower(trim((string) $k)))
            ->filter()
            ->flip();
        [$hgpOilItems, $hgpSparepartItems] = $this->splitOilSparepart($hgp?->items_json ?? [], $kodeOli);
        [$rsaHgpOilItems, $rsaHgpSparepartItems] = $this->splitOilSparepart($rsaHgp?->items_json ?? [], $kodeOli);

        $visibleTabs = $this->buildVisibleTabs($plan);
        $plafon = ($visibleTabs['plafon'] ?? true) ? $this->buildPlafonAnalisa($plan) : $this->emptyPlafonAnalisa($plan);

        // Dibungkus try/catch: tabel karyawans baru dibuat lewat migrasi manual
        // setelah deploy (lihat deploy.yml), jadi Report Audit tidak boleh ikut
        // 500 kalau migrasi itu belum sempat dijalankan.
        try {
            $karyawans = Karyawan::where('unit_usaha', $plan->cabang)->orderBy('nama')->get();
        } catch (\Throwable $e) {
            $karyawans = collect();
        }

        return compact(
            'plan', 'plafon', 'kas', 'smh', 'perlengkapan', 'bank', 'materai',
            'bpkbOnhand', 'bpkbInproses', 'kwitansi', 'piutangReguler',
            'piutangCdn', 'ttpGantung', 'cekFisik', 'mt', 'hgp', 'rsaHgp', 'hga',
            'smhTarikan', 'lampiran', 'lampiranEmbeds', 'mutasiPembelian', 'ttpCsc', 'visibleTabs', 'auditors', 'blankos',
            'perlengkapanOnhand', 'hgpOilItems', 'hgpSparepartItems',
            'rsaHgpOilItems', 'rsaHgpSparepartItems', 'karyawans'
        );
    }

    /**
     * Pecah item HGP/RSA HGP jadi [oilItems, sparepartItems] — hanya yang
     * selisihnya tidak nol. Nomor baris (NO) di masing-masing tabel dimulai
     * dari 1 sendiri-sendiri (lihat partials/rekap-selisih-table) — bukan
     * memakai posisi item di daftar lengkap.
     *
     * @return array{0: array, 1: array}
     */
    private function splitOilSparepart(array $items, \Illuminate\Support\Collection $kodeOli): array
    {
        $oilItems = [];
        $sparepartItems = [];

        foreach ($items as $it) {
            $selisih = (float) ($it['selisih'] ?? 0);
            if ($selisih === 0.0) {
                continue;
            }

            $row = [...$it, 'selisih' => $selisih];
            $kode = strtolower(trim((string) ($it['noPart'] ?? '')));

            if ($kode !== '' && $kodeOli->has($kode)) {
                $oilItems[] = $row;
            } else {
                $sparepartItems[] = $row;
            }
        }

        return [$oilItems, $sparepartItems];
    }

    // Bangun peta tab_key => tampil/tidak, mengikuti konfigurasi admin di
    // Database -> Jenis Audit & Tools. Default tampil jika belum dikonfigurasi.
    private function buildVisibleTabs(PlanAudit $plan): array
    {
        $modul = 'audit';
        if ($plan->is_mandiri) {
            $mandiri = PlanAuditMandiri::query()->where('plan_audit_id', $plan->id)->first();
            $modul = $mandiri?->jenis_pemeriksaan ?? 'audit_mandiri';
        }

        $allTabs = array_keys(config('audit_tabs', []));

        $overrides = AuditTabConfig::query()
            ->where('modul', $modul)
            ->where('jenis_audit', $plan->jenis_audit)
            ->pluck('visible', 'tab_key');

        return collect($allTabs)->mapWithKeys(fn($key) => [$key => $overrides->has($key) ? (bool) $overrides[$key] : true])->all();
    }

    private function emptyPlafonAnalisa(PlanAudit $plan): array
    {
        return [
            'cabang' => $plan->cabang ?? '',
            'namaUnit' => $plan->cabang ?? '',
            'wilayah' => '-',
            'plafonNilai' => null,
            'plafonNama' => null,
            'totalUnit' => 0,
            'totalNilai' => 0,
            'sisaTotal' => null,
            'persentase' => null,
            'perUnit' => [],
        ];
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
