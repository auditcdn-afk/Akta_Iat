<?php

namespace Tests\Feature;

use App\Models\DbAhmOil;
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
 * SPAREPART (tidak terdaftar), sambil nomor barisnya tetap mengacu ke posisi
 * asli di daftar lengkap (bukan dinomori ulang dari hasil filter).
 */
class RekapSelisihTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

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
        // Nomor baris tetap 1 (posisi asli di daftar lengkap), bukan dinomori ulang.
        $this->assertMatchesRegularExpression('/<td>1<\/td>\s*<td>08232M99K8LN0<\/td>/', $oilBagian);

        $sparepartBagian = substr($rekapBagian, strpos($rekapBagian, 'SPAREPART'));
        $this->assertStringContainsString('BATTERY', $sparepartBagian);
        $this->assertStringNotContainsString('SCOOTER GEAR OIL', $sparepartBagian);
        // Battery ada di posisi ke-2 pada daftar lengkap, bukan ke-1 pada hasil filter.
        $this->assertMatchesRegularExpression('/<td>2<\/td>\s*<td>31500KZR602<\/td>/', $sparepartBagian);
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
}
