<?php

namespace Tests\Feature;

use App\Models\DbAhmOil;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cetakan "Rekap Selisih Part & AHM Oil's" untuk tool HGP/RSA HGP — terpisah
 * dari Report Audit besar. Intinya: item dengan selisih 0 disembunyikan, dan
 * sisanya dipecah ke tabel AHM OIL'S (kode part terdaftar di db_ahm_oils) vs
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

        PemeriksaanAuditor::create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Abdul Aziz', 'nama_auditee' => 'Eka Ariani',
        ]);

        $html = $this->get(route('akta.rekap-selisih', [$this->plan, 'hgp']))->assertOk()->getContent();

        $this->assertStringContainsString("REKAP SELISIH PART &amp; AHM OIL'S", $html);
        $this->assertStringContainsString($this->plan->no_spt, $html);
        $this->assertStringContainsString('Abdul Aziz', $html);

        // Item selisih 0 tidak boleh muncul di halaman sama sekali.
        $this->assertStringNotContainsString('TIDAK ADA SELISIH', $html);

        $oilSection = $this->between($html, "AHM OIL'S", 'SPAREPART');
        $this->assertStringContainsString('SCOOTER GEAR OIL', $oilSection);
        $this->assertStringNotContainsString('BATTERY', $oilSection);
        // Nomor baris tetap 1 (posisi asli di daftar lengkap), bukan dinomori ulang.
        $this->assertMatchesRegularExpression('/<td>1<\/td>\s*<td>08232M99K8LN0<\/td>/', $oilSection);

        $sparepartSection = substr($html, strpos($html, 'SPAREPART'));
        $this->assertStringContainsString('BATTERY', $sparepartSection);
        $this->assertStringNotContainsString('SCOOTER GEAR OIL', $sparepartSection);
        // Battery ada di posisi ke-2 pada daftar lengkap, bukan ke-1 pada hasil filter.
        $this->assertMatchesRegularExpression('/<td>2<\/td>\s*<td>31500KZR602<\/td>/', $sparepartSection);
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

        $html = $this->get(route('akta.rekap-selisih', [$this->plan, 'rsa-hgp']))->assertOk()->getContent();
        $this->assertStringContainsString('OLI TEST', $html);
    }

    public function test_belum_ada_data_menampilkan_pesan_bukan_error(): void
    {
        $html = $this->get(route('akta.rekap-selisih', [$this->plan, 'hgp']))->assertOk()->getContent();
        $this->assertStringContainsString('Tidak ada selisih', $html);
    }

    public function test_tool_selain_hgp_dan_rsa_hgp_ditolak(): void
    {
        $this->get(route('akta.rekap-selisih', [$this->plan, 'kas']))->assertNotFound();
    }

    private function between(string $haystack, string $start, string $end): string
    {
        $startPos = strpos($haystack, $start);
        $endPos = strpos($haystack, $end, $startPos);

        return substr($haystack, $startPos, $endPos - $startPos);
    }
}
