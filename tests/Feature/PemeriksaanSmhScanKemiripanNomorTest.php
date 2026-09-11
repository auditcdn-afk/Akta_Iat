<?php

namespace Tests\Feature;

use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Scan unit di pemeriksaan SMH: dua unit BERBEDA bisa punya ekor nomor yang
 * sama — mis. no mesin unit B "JME1E 2361532" vs no rangka unit A
 * "JME124TK361532". Dulu scan selalu mengambil baris pertama yang nyangkut,
 * jadi unit B tidak pernah bisa dipilih. Sekarang kecocokan penuh menang atas
 * kecocokan ekor, dan kalau memang sama kuat semua kandidat dikembalikan
 * supaya auditor yang memilih.
 */
class PemeriksaanSmhScanKemiripanNomorTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;
    private SmhOnhandItem $unitA;
    private SmhOnhandItem $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0424/28/07/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);

        $smh = PemeriksaanSmh::query()->create([
            'plan_audit_id' => $this->plan->id,
            'no_spt'        => $this->plan->no_spt,
            'cabang'        => $this->plan->cabang,
        ]);

        // Unit A disimpan duluan: no RANGKA-nya berakhiran 361532.
        $this->unitA = SmhOnhandItem::create([
            'pemeriksaan_smh_id' => $smh->id,
            'no_mesin'           => 'JME1E 2361510',
            'no_rangka'          => 'JME124TK361532',
            'kode_model'         => 'MJ2',
        ]);

        // Unit B: no MESIN-nya yang berakhiran 2361532 — unit yang berbeda.
        $this->unitB = SmhOnhandItem::create([
            'pemeriksaan_smh_id' => $smh->id,
            'no_mesin'           => 'JME1E 2361532',
            'no_rangka'          => 'JME120TK361561',
            'kode_model'         => 'MJ2',
        ]);
    }

    public function test_no_mesin_persis_menang_atas_unit_lain_yang_ekor_rangkanya_sama(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=' . urlencode('JME1E 2361532') . '&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertFalse($res->json('ambiguous'));
        $this->assertSame($this->unitB->id, $res->json('data.id'));
        $this->assertSame('JME1E 2361532', $res->json('data.noMesin'));
    }

    public function test_scan_tanpa_spasi_tetap_menemukan_unit_yang_benar(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=JME1E2361532&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertSame($this->unitB->id, $res->json('data.id'));
    }

    public function test_no_rangka_persis_tetap_menemukan_unitnya_sendiri(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=JME124TK361532&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertSame($this->unitA->id, $res->json('data.id'));
    }

    public function test_potongan_4_angka_yang_cocok_ke_dua_unit_dikembalikan_semua(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=1532&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertTrue($res->json('ambiguous'));
        $this->assertNull($res->json('data'));

        $ids = collect($res->json('matches'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$this->unitA->id, $this->unitB->id], $ids);
    }

    public function test_item_id_memilih_unit_itu_persis_tanpa_pencarian_ulang(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=1532&item_id=' . $this->unitB->id . '&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertFalse($res->json('ambiguous'));
        $this->assertSame($this->unitB->id, $res->json('data.id'));
    }

    public function test_item_id_dari_plan_lain_tidak_bisa_diambil(): void
    {
        $planLain = PlanAudit::query()->create([
            'no_spt'      => '0425/28/07/2026/SPT-IAT',
            'cabang'      => 'CSC LAIN',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);

        $res = $this->getJson('/api/audit-detail/smh/scan?q=1532&item_id=' . $this->unitB->id . '&plan_audit_id=' . $planLain->id);

        $res->assertOk();
        $this->assertNull($res->json('data'));
    }

    public function test_barcode_format_lain_masih_ketemu_lewat_ekor_5_karakter(): void
    {
        // Barcode rangka fisik pakai format panjang, data onhand versi ringkas —
        // hanya ekor nomornya yang sama. Selama cuma satu unit yang nyangkut,
        // unit itu tetap langsung terbuka seperti sebelumnya.
        SmhOnhandItem::create([
            'pemeriksaan_smh_id' => $this->unitA->pemeriksaan_smh_id,
            'no_mesin'           => 'KD11E 1722826',
            'no_rangka'          => 'KD1112TK722826',
        ]);

        $res = $this->getJson('/api/audit-detail/smh/scan?q=MH1KFG112TK722826&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertFalse($res->json('ambiguous'));
        $this->assertSame('KD1112TK722826', $res->json('data.noRangka'));
    }

    public function test_dua_no_mesin_dengan_ekor_sama_tidak_ditebak_salah_satu(): void
    {
        $unitC = SmhOnhandItem::create([
            'pemeriksaan_smh_id' => $this->unitA->pemeriksaan_smh_id,
            'no_mesin'           => 'JME1E 2401532',
            'no_rangka'          => 'JME129TK401599',
        ]);

        $res = $this->getJson('/api/audit-detail/smh/scan?q=1532&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertNull($res->json('data'));
        $this->assertTrue($res->json('ambiguous'));

        $ids = collect($res->json('matches'))->pluck('id')->all();
        $this->assertContains($this->unitB->id, $ids);
        $this->assertContains($unitC->id, $ids);
    }

    public function test_unit_tidak_ada_tetap_melaporkan_tidak_ditemukan(): void
    {
        $res = $this->getJson('/api/audit-detail/smh/scan?q=ZZZZ9999999&plan_audit_id=' . $this->plan->id);

        $res->assertOk();
        $this->assertNull($res->json('data'));
        $this->assertFalse($res->json('ambiguous'));
    }
}
