<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use App\Services\PerlengkapanOnhand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Endpoint rekap gabungan perlengkapan per jenis.
 *
 * Alasannya ada: teks Rekomendasi yang terisi otomatis dulu menghitung sendiri
 * angka ini di browser, dan memakai kolom "total" dari smh-summary (jumlah unit
 * yang checklist-nya SUDAH diisi) sebagai saldo — sementara Report Audit memakai
 * "totalOnhand" (SELURUH unit yang membutuhkan perlengkapan itu).
 *
 * Selama seluruh unit sudah diperiksa keduanya kebetulan sama, jadi selisihnya
 * tidak kelihatan. Begitu ada unit yang belum/tidak sempat diperiksa, rekomendasi
 * resmi memunculkan selisih yang tidak ada di laporannya — persis keluhan yang
 * ditemukan di lapangan: Baterai 3 Ah tertulis selisih 4 di Rekomendasi, 0 di
 * Report Audit.
 *
 * Sekarang angkanya dilayani server dari PerlengkapanOnhand::rekapGabungan(),
 * sumber yang sama dengan Report Audit dan tombol Export Selisih.
 */
class PerlengkapanRekapGabunganApiTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor1', 'role' => 'auditor', 'unit_usaha' => 'CVSK H1',
        ]));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0482/01/09/2026/SPT-IAT', 'cabang' => 'CVSK H1',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        DbUnitUsaha::query()->create(['unit_usaha' => 'CVSK H1', 'wilayah' => 'RIAU', 'jenis' => 'H1']);
        DbPerlengkapan::query()->create([
            'kode' => 'JBK1E', 'wilayah' => 'RIAU', 'nama' => 'BeAT',
            'keterangan' => 'Helm Open Face',
        ]);

        $smh = PemeriksaanSmh::query()->create([
            'plan_audit_id' => $this->plan->id,
            'no_spt' => $this->plan->no_spt,
            'cabang' => 'CVSK H1',
        ]);

        // TIGA unit BeAT onhand — semuanya membutuhkan Helm Open Face.
        // Hanya DUA yang sempat diperiksa checklist-nya, dan dari dua itu hanya
        // satu helmnya ketemu. Unit ketiga belum diperiksa sama sekali: helmnya
        // tetap kurang, dan kekurangan itu tidak boleh hilang dari laporan hanya
        // karena unitnya belum sempat disentuh auditor.
        foreach ([['JBK1E1000001', true], ['JBK1E1000002', false]] as [$noMesin, $helmAda]) {
            SmhOnhandItem::query()->create([
                'pemeriksaan_smh_id' => $smh->id,
                'no_mesin'           => $noMesin,
                'no_rangka'          => 'MH1'.$noMesin,
                'status_fisik'       => 'ada',
                'perlengkapan_json'  => [['nama' => 'Helm Open Face', 'ada' => $helmAda]],
            ]);
        }

        SmhOnhandItem::query()->create([
            'pemeriksaan_smh_id' => $smh->id,
            'no_mesin'           => 'JBK1E1000003',
            'no_rangka'          => 'MH1JBK1E1000003',
            'status_fisik'       => 'belum',
            'perlengkapan_json'  => null,
        ]);

        // Dua helm ditemukan terpisah di gudang, menutup dua unit yang kurang.
        PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Helm Open Face',
            'fisik'              => 2,
            'saldo'              => 2,
            'selisih'            => 0,
            'penjelasan'         => 'Ditemukan di gudang',
        ]);
    }

    private function rekap(): array
    {
        return $this->getJson('/api/audit-detail/perlengkapan/rekap-gabungan?plan_audit_id='.$this->plan->id)
            ->assertOk()
            ->json('data');
    }

    public function test_prasyarat_ada_unit_yang_belum_diperiksa(): void
    {
        // Kalau kedua angka ini sama, skenarionya tidak menguji apa pun —
        // justru perbedaan inilah yang dulu membuat Rekomendasi meleset.
        $ringkasan = app(PerlengkapanOnhand::class)->summaryPerJenis((string) $this->plan->id)['Helm Open Face'];

        $this->assertSame(3, $ringkasan['totalOnhand'], 'Tiga unit membutuhkan helm.');
        $this->assertSame(2, $ringkasan['total'], 'Hanya dua unit yang checklist-nya terisi.');
    }

    public function test_saldo_memakai_seluruh_unit_onhand_bukan_yang_sudah_diperiksa(): void
    {
        $helm = collect($this->rekap())->firstWhere('jenis', 'Helm Open Face');

        $this->assertSame(3, $helm['smhSaldo'], 'Saldo = seluruh unit yang membutuhkan, termasuk yang belum diperiksa.');
        $this->assertSame(1, $helm['smhFisik']);
        $this->assertSame(2, $helm['luarFisik']);
    }

    public function test_tidak_memunculkan_selisih_palsu_untuk_unit_yang_belum_diperiksa(): void
    {
        $helm = collect($this->rekap())->firstWhere('jenis', 'Helm Open Face');

        // (1 menempel di unit + 2 di gudang) - 3 unit yang membutuhkan = 0.
        // Cara lama memakai 2 sebagai saldo dan melaporkan selisih +1 yang
        // tidak pernah ada di Report Audit.
        $this->assertSame(0, $helm['totalSelisih']);
    }

    public function test_isinya_sama_persis_dengan_yang_dipakai_report_audit(): void
    {
        $laporan = app(PerlengkapanOnhand::class)->rekapGabungan(
            (string) $this->plan->id,
            PemeriksaanPerlengkapan::where('plan_audit_id', $this->plan->id)->get()
        );

        $this->assertEquals($laporan, $this->rekap(),
            'Endpoint ini dan Report Audit harus memakai perhitungan yang sama persis, bukan sekadar mirip.');
    }

    public function test_angka_endpoint_sama_dengan_export_excel(): void
    {
        // Export Selisih menyaring baris berselisih nol, jadi yang dibandingkan
        // adalah baris yang tersisa — sumbernya tetap harus satu.
        $berselisih = collect($this->rekap())->filter(fn ($r) => $r['totalSelisih'] != 0)->values();

        $this->get('/api/audit-detail/perlengkapan/export-selisih?plan_audit_id='.$this->plan->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertCount(0, $berselisih,
            'Pada skenario ini semuanya seimbang, jadi tidak ada baris yang perlu ditindaklanjuti.');
    }

    public function test_plan_tanpa_data_mengembalikan_daftar_kosong_bukan_error(): void
    {
        $lain = PlanAudit::query()->create([
            'no_spt' => '0999/01/09/2026/SPT-IAT', 'cabang' => 'CVSK H1',
            'jenis_audit' => 'Audit Full SO', 'status' => 'draft',
        ]);

        $this->getJson('/api/audit-detail/perlengkapan/rekap-gabungan?plan_audit_id='.$lain->id)
            ->assertOk()
            ->assertJson(['data' => []]);
    }
}
