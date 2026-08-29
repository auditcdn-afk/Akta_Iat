<?php

namespace Tests\Feature;

use App\Models\DbAhmOil;
use App\Models\PemeriksaanHga;
use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Rekap Selisih Part & AHM Oil's" — BUKAN cetakan terpisah, melainkan bagian
 * dari section HGP & AHM Oils / RSA HGP & AHM Oils pada Report Audit besar
 * (lihat riwayat: sempat dibuat sebagai halaman/tombol cetak sendiri, tapi
 * pengguna minta digabung jadi satu kesatuan dengan laporan stok
 * keseluruhannya).
 *
 * Intinya: item dengan selisih 0 disembunyikan dari rekap ini, dan sisanya
 * dipecah ke tabel AHM OIL'S (kode part terdaftar di db_ahm_oils) vs
 * SPAREPART (tidak terdaftar). Nomor baris (NO) mulai dari 1 di
 * masing-masing tabel, dan tiap tabel diakhiri baris TOTAL (Sistem, Fisik,
 * Selisih, dan nilai uang HET dijumlahkan).
 */
class RekapSelisihTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0433/28/07/2026/SPT-IAT', 'cabang' => 'CSC PRW',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
            'kepala_tim' => 'Abdul Aziz', 'tim' => ['Abdul Aziz'],
        ]);
    }

    private function html(): string
    {
        return $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();
    }

    /** Potongan HTML dari satu judul sampai judul berikutnya (lihat ReportPdfLebarTabelTest). */
    private function section(string $html, string $judul): string
    {
        $mulai = strpos($html, $judul);
        $this->assertNotFalse($mulai, "Section \"{$judul}\" tidak ada di laporan.");

        preg_match_all(
            '/<div class="section(?!-title)[^"]*">/',
            substr($html, 0, $mulai),
            $cocok,
            PREG_OFFSET_CAPTURE
        );
        $this->assertNotEmpty($cocok[0], "Pembuka section untuk \"{$judul}\" tidak ketemu.");

        $buka  = end($cocok[0])[1];
        $akhir = strpos($html, '<div class="section-title">', $mulai + strlen($judul));

        return substr($html, $buka, ($akhir ?: strlen($html)) - $buka);
    }

    public function test_item_terdaftar_di_db_ahm_oil_masuk_rekap_oil_sisanya_sparepart(): void
    {
        DbAhmOil::create(['kode' => '08232M99K8LN0', 'nama' => 'SCOOTER GEAR OIL']);

        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                // NO 1 (index 0): kode part terdaftar di AHM Oil, ada selisih -> masuk AHM OIL'S
                ['noPart' => '08232M99K8LN0', 'sparepart' => 'SCOOTER GEAR OIL (120ML)REP', 'saldoAkhir' => 349, 'fisik' => 347, 'selisih' => -2, 'hargaHet' => 16500],
                // NO 2 (index 1): tidak terdaftar, ada selisih -> masuk SPAREPART
                ['noPart' => '31500KZR602', 'sparepart' => 'BATTERY(GTZ6V)', 'saldoAkhir' => 8, 'fisik' => 7, 'selisih' => -1, 'hargaHet' => 302000],
                // NO 3 (index 2): selisih 0 -> TIDAK muncul di rekap sama sekali
                ['noPart' => '99999XXX', 'sparepart' => 'TIDAK ADA SELISIH', 'saldoAkhir' => 5, 'fisik' => 5, 'selisih' => 0, 'hargaHet' => 1000],
            ],
        ]);

        $section = $this->section($this->html(), '14. HGP &amp; AHM OILS');

        $this->assertStringContainsString("REKAP SELISIH PART &amp; AHM OIL'S", $section);

        // Item selisih 0 tidak boleh muncul di rekap (masih boleh muncul di
        // tabel lengkap HGP di atasnya, makanya dicek posisinya di section
        // saja, bukan menuntut hilang total dari section).
        $rekapAwal = strpos($section, "REKAP SELISIH PART &amp; AHM OIL'S");
        $rekapBagian = substr($section, $rekapAwal);
        $this->assertStringNotContainsString('TIDAK ADA SELISIH', $rekapBagian);

        $oilBagian = substr($rekapBagian, 0, strpos($rekapBagian, 'SPAREPART'));
        $this->assertStringContainsString('SCOOTER GEAR OIL', $oilBagian);
        $this->assertStringNotContainsString('BATTERY', $oilBagian);
        // Nomor baris mulai dari 1 di tabel AHM OIL'S sendiri.
        $this->assertMatchesRegularExpression('/<td>1<\/td>\s*<td>08232M99K8LN0<\/td>/', $oilBagian);
        // TOTAL: selisih -2 x HET 16.500 = -33.000.
        $this->assertStringContainsString('TOTAL', $oilBagian);
        $this->assertStringContainsString('33.000', $oilBagian);

        $sparepartBagian = substr($rekapBagian, strpos($rekapBagian, 'SPAREPART'));
        $this->assertStringContainsString('BATTERY', $sparepartBagian);
        $this->assertStringNotContainsString('SCOOTER GEAR OIL', $sparepartBagian);
        // Battery adalah satu-satunya baris di tabel SPAREPART -> nomornya
        // tetap 1 (mulai dari 1 lagi), BUKAN 2 walau posisinya di daftar
        // lengkap HGP adalah item ke-2.
        $this->assertMatchesRegularExpression('/<td>1<\/td>\s*<td>31500KZR602<\/td>/', $sparepartBagian);
        // TOTAL: selisih -1 x HET 302.000 = -302.000.
        $this->assertStringContainsString('TOTAL', $sparepartBagian);
        $this->assertStringContainsString('302.000', $sparepartBagian);
    }

    public function test_rsa_hgp_ikut_aturan_yang_sama(): void
    {
        DbAhmOil::create(['kode' => 'OIL001', 'nama' => 'OLI TEST']);

        PemeriksaanRsaHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'OIL001', 'sparepart' => 'OLI TEST', 'saldoAkhir' => 10, 'fisik' => 9, 'selisih' => -1, 'hargaHet' => 20000],
            ],
        ]);

        $section = $this->section($this->html(), '14B. RSA HGP');
        $this->assertStringContainsString("REKAP SELISIH PART &amp; AHM OIL'S", $section);
        $this->assertStringContainsString('OLI TEST', $section);
    }

    public function test_total_menjumlahkan_semua_baris_bukan_cuma_baris_terakhir(): void
    {
        DbAhmOil::create(['kode' => 'OIL-A', 'nama' => 'OLI A']);
        DbAhmOil::create(['kode' => 'OIL-B', 'nama' => 'OLI B']);

        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'OIL-A', 'sparepart' => 'OLI A', 'saldoAkhir' => 100, 'fisik' => 98, 'selisih' => -2, 'hargaHet' => 10000],
                ['noPart' => 'OIL-B', 'sparepart' => 'OLI B', 'saldoAkhir' => 50, 'fisik' => 53, 'selisih' => 3, 'hargaHet' => 5000],
            ],
        ]);

        $section = $this->section($this->html(), '14. HGP &amp; AHM OILS');
        $oilBagian = substr($section, strpos($section, "REKAP SELISIH PART &amp; AHM OIL'S"));
        $oilBagian = substr($oilBagian, 0, strpos($oilBagian, 'SPAREPART'));

        // Sistem: 100+50=150, Fisik: 98+53=151, Selisih: -2+3=1,
        // Nilai: (-2*10.000) + (3*5.000) = -20.000 + 15.000 = -5.000.
        $totalRow = substr($oilBagian, strpos($oilBagian, 'TOTAL'));
        $this->assertMatchesRegularExpression('/150.*151.*\b1\b.*5.000/s', $totalRow);
    }

    public function test_tidak_ada_selisih_maka_rekap_tidak_ditampilkan(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'X1', 'sparepart' => 'ITEM PAS', 'saldoAkhir' => 5, 'fisik' => 5, 'selisih' => 0, 'hargaHet' => 1000],
            ],
        ]);

        $section = $this->section($this->html(), '14. HGP &amp; AHM OILS');
        $this->assertStringNotContainsString("REKAP SELISIH PART &amp; AHM OIL'S", $section);
    }

    /**
     * HGA (Accessories) tidak punya pemisahan AHM OIL'S vs SPAREPART seperti
     * HGP/RSA HGP (semua itemnya aksesoris) — jadi cukup satu tabel "REKAP
     * SELISIH HGA" berisi item yang selisihnya tidak nol saja, dengan pola
     * sembunyi/tampil dan total yang sama seperti rekap HGP.
     */
    public function test_hga_punya_rekap_selisih_sendiri(): void
    {
        PemeriksaanHga::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'X1', 'sparepart' => 'HELM CROSS', 'saldoAkhir' => 10, 'fisik' => 8, 'selisih' => -2, 'hargaHet' => 100000],
                ['noPart' => 'X2', 'sparepart' => 'HELM RETRO', 'saldoAkhir' => 5, 'fisik' => 5, 'selisih' => 0, 'hargaHet' => 90000],
                ['noPart' => 'X3', 'sparepart' => 'KACA SPION', 'saldoAkhir' => 3, 'fisik' => 4, 'selisih' => 1, 'hargaHet' => 50000],
            ],
        ]);

        $section = $this->section($this->html(), '15. HGA (Accessories)');
        $this->assertStringContainsString('REKAP SELISIH HGA', $section);

        $rekapBagian = substr($section, strpos($section, 'REKAP SELISIH HGA'));
        $this->assertStringContainsString('HELM CROSS', $rekapBagian);
        $this->assertStringContainsString('KACA SPION', $rekapBagian);
        // Item tanpa selisih tidak ikut masuk ke rekap (masih boleh muncul di
        // tabel lengkap HGA di atasnya, makanya dicek dari titik rekap saja).
        $this->assertStringNotContainsString('HELM RETRO', $rekapBagian);

        // TOTAL: selisih (-2)+1=-1, nilai (-2*100.000)+(1*50.000)=-150.000.
        $totalRow = substr($rekapBagian, strpos($rekapBagian, 'TOTAL'));
        $this->assertStringContainsString('150.000', $totalRow);
    }

    public function test_hga_tanpa_selisih_maka_rekap_tidak_ditampilkan(): void
    {
        PemeriksaanHga::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'X1', 'sparepart' => 'ITEM PAS', 'saldoAkhir' => 5, 'fisik' => 5, 'selisih' => 0, 'hargaHet' => 1000],
            ],
        ]);

        $section = $this->section($this->html(), '15. HGA (Accessories)');
        $this->assertStringNotContainsString('REKAP SELISIH HGA', $section);
    }
}
