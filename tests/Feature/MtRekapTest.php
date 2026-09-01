<?php

namespace Tests\Feature;

use App\Models\DbMt;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanMt;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\MtRekapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auditor melampirkan contoh laporan lama "AUDIT MT — REKAP TOOLS RUSAK /
 * HILANG": tabel per mekanik (Kode Tool, Nama Tool, kolom
 * Bagus/SK Audit/Rusak/Hilang berupa centang, Harga), didahului header
 * idplan/unit usaha/pembuat/tim, dan minta itu direplikasi ke Report Audit
 * PDF plus tombol cetak tersendiri di tab pemeriksaan MT.
 *
 * Data pemeriksaan MT (PemeriksaanMt::data_json) hanya menyimpan NAMA tool
 * per kategori — bukan kode maupun harganya. MtRekapBuilder yang
 * menyambungkannya ke katalog db_mt (dicocokkan lewat nama, DI DALAM jenis
 * yang sama — MT Baru/Lama/FI — karena kode & harga bisa beda per jenis).
 */
class MtRekapTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'admin', 'display_name' => 'Abdul Aziz']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0034/01/01/2026/SPT-IAT', 'cabang' => 'CSC SLP',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);
    }

    private function seedKatalog(): void
    {
        DbMt::create(['nama_singkat' => 'KT10', 'nama_peralatan' => 'Kunci T Fleksibel 10 mm (Lama)', 'kode_peralatan' => '07600-KL4-4500_LM', 'harga' => 558330, 'jenis' => 'MT Lama']);
        DbMt::create(['nama_singkat' => 'KT12', 'nama_peralatan' => 'Kunci T fleksibel 12 mm (Lama)', 'kode_peralatan' => '07600-KL4-4600_LM', 'harga' => 612720, 'jenis' => 'MT Lama']);
        DbMt::create(['nama_singkat' => 'OBK', 'nama_peralatan' => 'Obeng (kayu) minus 100 mm (Lama)', 'kode_peralatan' => '07600-KLH-1630_LM', 'harga' => 138750, 'jenis' => 'MT Lama']);
        // Nama yang sama persis tapi di katalog MT Baru punya kode & harga beda
        // — memastikan pencocokan memang terkunci ke jenis yang sedang aktif.
        DbMt::create(['nama_singkat' => 'KT10B', 'nama_peralatan' => 'Kunci T Fleksibel 10 mm (Lama)', 'kode_peralatan' => 'KODE-BEDA-DI-BARU', 'harga' => 999999, 'jenis' => 'MT Baru']);
    }

    public function test_rekap_mengelompokkan_per_mekanik_dan_mencocokkan_kode_harga_dari_katalog(): void
    {
        $this->seedKatalog();

        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['SOFIAN EFENDI' => 'lama'],
                'entries' => [[
                    'mekanik' => 'SOFIAN EFENDI', 'jenis' => 'lama',
                    'bagus' => [], 'skAudit' => [],
                    'rusak' => ['Kunci T Fleksibel 10 mm (Lama)', 'Kunci T fleksibel 12 mm (Lama)', 'Obeng (kayu) minus 100 mm (Lama)'],
                    'hilang' => [],
                    'keterangan' => 'CDN-SK/2025.212/IAT',
                ]],
            ],
        ]);

        $rekap = app(MtRekapBuilder::class)->build(PemeriksaanMt::first());

        $this->assertArrayHasKey('SOFIAN EFENDI', $rekap['rusak']);
        $this->assertSame([], $rekap['hilang']);

        $mekanik = $rekap['rusak']['SOFIAN EFENDI'];
        $this->assertSame('CDN-SK/2025.212/IAT', $mekanik['keterangan']);
        $this->assertCount(3, $mekanik['rows']);

        $baris = collect($mekanik['rows'])->keyBy('nama');
        // Dicocokkan ke jenis 'lama' (MT Lama), BUKAN ke baris kembar di MT Baru.
        $this->assertSame('07600-KL4-4500_LM', $baris['Kunci T Fleksibel 10 mm (Lama)']['kode']);
        $this->assertEquals(558330.0, $baris['Kunci T Fleksibel 10 mm (Lama)']['harga']);
        $this->assertSame('07600-KL4-4600_LM', $baris['Kunci T fleksibel 12 mm (Lama)']['kode']);
        $this->assertEquals(612720.0, $baris['Kunci T fleksibel 12 mm (Lama)']['harga']);
    }

    public function test_tool_yang_tidak_ada_di_katalog_tetap_muncul_dengan_kode_dan_harga_kosong(): void
    {
        // Tanpa seedKatalog() — katalognya kosong sama sekali.
        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['BUDI' => 'baru'],
                'entries' => [[
                    'mekanik' => 'BUDI', 'jenis' => 'baru',
                    'bagus' => [], 'skAudit' => [], 'rusak' => [], 'hilang' => ['Tool Yang Tidak Terdaftar'],
                    'keterangan' => '',
                ]],
            ],
        ]);

        $rekap = app(MtRekapBuilder::class)->build(PemeriksaanMt::first());

        $baris = $rekap['hilang']['BUDI']['rows'][0];
        $this->assertSame('Tool Yang Tidak Terdaftar', $baris['nama']);
        $this->assertSame('', $baris['kode']);
        $this->assertNull($baris['harga']);
    }

    /** Mekanik punya entry di 3 jenis sekaligus — hanya jenis yang SEDANG DIPILIH auditor yang boleh masuk rekap. */
    public function test_hanya_entry_jenis_yang_sedang_aktif_dipilih_yang_masuk_rekap(): void
    {
        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['ANDI' => 'fi'],
                'entries' => [
                    ['mekanik' => 'ANDI', 'jenis' => 'baru', 'rusak' => ['Tool Baru'], 'hilang' => [], 'bagus' => [], 'skAudit' => [], 'keterangan' => ''],
                    ['mekanik' => 'ANDI', 'jenis' => 'fi', 'rusak' => ['Tool FI'], 'hilang' => [], 'bagus' => [], 'skAudit' => [], 'keterangan' => ''],
                ],
            ],
        ]);

        $rekap = app(MtRekapBuilder::class)->build(PemeriksaanMt::first());

        $this->assertCount(1, $rekap['rusak']['ANDI']['rows']);
        $this->assertSame('Tool FI', $rekap['rusak']['ANDI']['rows'][0]['nama']);
    }

    public function test_tanpa_data_mt_rekap_kosong_tapi_tidak_error(): void
    {
        $rekap = app(MtRekapBuilder::class)->build(null);
        $this->assertSame(['rusak' => [], 'hilang' => []], $rekap);
    }

    public function test_totalbaris_menghitung_seluruh_mekanik_dalam_satu_kategori(): void
    {
        $builder = app(MtRekapBuilder::class);
        $this->assertSame(0, $builder->totalBaris([]));
        $this->assertSame(3, $builder->totalBaris([
            'A' => ['keterangan' => '', 'rows' => [1, 2]],
            'B' => ['keterangan' => '', 'rows' => [3]],
        ]));
    }

    public function test_report_audit_pdf_menampilkan_rekap_rusak_dan_hilang(): void
    {
        $this->seedKatalog();
        // nama_auditor tidak dikirim dari form — selalu diambil server-side dari
        // display_name akun yang sedang login (lihat PemeriksaanAuditorController).
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id, 'tool' => 'mt', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();

        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['SOFIAN EFENDI' => 'lama'],
                'entries' => [[
                    'mekanik' => 'SOFIAN EFENDI', 'jenis' => 'lama',
                    'bagus' => [], 'skAudit' => [],
                    'rusak' => ['Kunci T Fleksibel 10 mm (Lama)'],
                    'hilang' => ['Kunci T fleksibel 12 mm (Lama)'],
                    'keterangan' => 'CDN-SK/2025.212/IAT',
                ]],
            ],
        ]);

        $html = $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();

        $this->assertStringContainsString('REKAP TOOLS RUSAK', $html);
        $this->assertStringContainsString('REKAP TOOLS HILANG', $html);
        $this->assertStringContainsString('AUDIT MT', $html);
        $this->assertStringContainsString('SOFIAN EFENDI', $html);
        $this->assertStringContainsString('07600-KL4-4500_LM', $html);
        $this->assertStringContainsString('558.330', $html);
        $this->assertStringContainsString('CDN-SK/2025.212/IAT', $html);
        $this->assertStringContainsString('CSC SLP', $html);
        $this->assertStringContainsString('0034/01/01/2026/SPT-IAT', $html);
        $this->assertStringContainsString('Abdul Aziz', $html);
    }

    public function test_report_audit_pdf_rekap_tampil_kosong_bukan_error_saat_tidak_ada_tool_rusak_hilang(): void
    {
        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['BUDI' => 'baru'],
                'entries' => [[
                    'mekanik' => 'BUDI', 'jenis' => 'baru',
                    'bagus' => ['Tool Aman'], 'skAudit' => [], 'rusak' => [], 'hilang' => [],
                    'keterangan' => '',
                ]],
            ],
        ]);

        $html = $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();

        $this->assertStringContainsString('Tidak ada tools rusak', $html);
        $this->assertStringContainsString('Tidak ada tools hilang', $html);
    }

    public function test_halaman_cetak_mandiri_rekap_bisa_dibuka(): void
    {
        $this->seedKatalog();
        PemeriksaanMt::create([
            'plan_audit_id' => $this->plan->id,
            'data_json' => [
                'mekanikSelectedJenis' => ['SOFIAN EFENDI' => 'lama'],
                'entries' => [[
                    'mekanik' => 'SOFIAN EFENDI', 'jenis' => 'lama',
                    'bagus' => [], 'skAudit' => [], 'rusak' => ['Kunci T Fleksibel 10 mm (Lama)'], 'hilang' => [],
                    'keterangan' => '',
                ]],
            ],
        ]);

        $html = $this->get(route('akta.report-audit.mt-rekap', $this->plan))->assertOk()->getContent();

        $this->assertStringContainsString('REKAP TOOLS RUSAK', $html);
        $this->assertStringContainsString('07600-KL4-4500_LM', $html);
        // Tanpa ?autoprint=1 — jangan otomatis mencetak begitu dibuka (tombol
        // manual "Cetak / Save PDF" tetap ada & tetap boleh memanggil
        // window.print(), yang tidak boleh ada adalah skrip yang MEMICUNYA sendiri).
        $this->assertStringNotContainsString("addEventListener('load'", $html);
    }

    public function test_halaman_cetak_mandiri_tanpa_data_mt_tidak_error(): void
    {
        $this->get(route('akta.report-audit.mt-rekap', $this->plan))
            ->assertOk()
            ->assertSeeText('Tidak ada tools rusak');
    }
}
