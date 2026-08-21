<?php

namespace Tests\Feature;

use App\Models\PemeriksaanHga;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Section HGP, RSA HGP, HGA, dan SMH Tarikan punya tabel 12 kolom dengan lebar
 * minimum 800–900px. Di A4 tegak (±695px area cetak) tabel itu tidak muat, dan
 * kolom paling kanan — "Jumlah (Rp)" dan "Keterangan" — HILANG SAMA SEKALI dari
 * PDF, bukan sekadar terpotong secara visual. Karena itu section-nya dicetak
 * melintang, mengikuti pola yang sudah dipakai Piutang Reguler/CDN.
 *
 * Tapi .section-landscape juga memaksa ganti halaman sebelum & sesudahnya, jadi
 * kelasnya hanya dipasang kalau section itu memang ada isinya — kalau tidak,
 * satu halaman melintang terbuang hanya untuk tulisan "Belum ada data".
 */
class ReportPdfLebarTabelTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'SBG MTR',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);
    }

    private function html(): string
    {
        return $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();
    }

    /** Potongan HTML satu section, dari judulnya sampai judul section berikutnya. */
    private function section(string $html, string $judul): string
    {
        $mulai = strpos($html, $judul);
        $this->assertNotFalse($mulai, "Section \"{$judul}\" tidak ada di laporan.");

        // Mundur ke pembuka <div class="section ..."> milik section ini. Pencarian
        // harus mengabaikan <div class="section-title"> — itu justru elemen yang
        // sedang kita pijak, sehingga pencarian mundur polos selalu berhenti di situ.
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

    public function test_section_hgp_dicetak_melintang_saat_ada_data(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [[
                'noPart' => '06435K84N01', 'namaPart' => 'PAD SET, RR.',
                'saldoAkhir' => 5, 'fisik' => 5, 'wo' => 0, 'hargaHet' => 145500,
                'keterangan' => 'A1.01.4.7 (F)',
            ]],
        ]);

        $section = $this->section($this->html(), '14. HGP &amp; AHM OILS');

        $this->assertStringContainsString('section-landscape', $section);

        // Kolom paling kanan — yang dulu hilang dari PDF — harus tetap dirender.
        $this->assertStringContainsString('Jumlah (Rp)', $section);
        $this->assertStringContainsString('Keterangan', $section);
    }

    public function test_section_hgp_tetap_tegak_saat_kosong(): void
    {
        $section = $this->section($this->html(), '14. HGP &amp; AHM OILS');

        $this->assertStringNotContainsString(
            'section-landscape',
            $section,
            'Section kosong tidak boleh memakai halaman melintang sendiri.'
        );
    }

    public function test_section_hga_ikut_aturan_yang_sama(): void
    {
        PemeriksaanHga::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [['noPart' => 'X1', 'namaPart' => 'HELM', 'saldoAkhir' => 1, 'fisik' => 1]],
        ]);

        $section = $this->section($this->html(), '15. HGA (Accessories)');
        $this->assertStringContainsString('section-landscape', $section);
    }

    /**
     * Kontainer geser-samping berguna di layar, tapi saat dicetak bagian yang
     * harus digeser tidak ikut tercetak. Aturan cetaknya harus tetap ada.
     */
    public function test_kontainer_geser_dinetralkan_saat_dicetak(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [['noPart' => 'X1', 'namaPart' => 'PAD SET', 'saldoAkhir' => 1, 'fisik' => 1]],
        ]);

        $html = $this->html();

        // Aturan cetaknya selalu ada; kontainernya baru muncul kalau ada tabelnya.
        $this->assertStringContainsString('.tbl-scroll { overflow: visible !important; }', $html);
        $this->assertStringContainsString('class="tbl-scroll"', $html);
    }
}
