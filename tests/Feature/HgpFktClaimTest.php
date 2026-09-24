<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Aturan gudang (WHS): Faktur Belum Kutip mengurangi Saldo Akhir, Claim
 * menambah Fisik.
 *
 * Dibuat sebagai saklar PER PLAN AUDIT dan MATI secara bawaan, sebab aturan
 * ini hanya untuk data WHS yang sudah terlanjur diinput dan ke depan tidak
 * berlaku lagi. Yang paling dijaga di sini justru batasnya: plan lain, plan
 * baru, dan berkas onhand cabang tidak boleh ikut terpengaruh.
 */
class HgpFktClaimTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0500/01/01/2026/SPT-IAT', 'cabang' => 'WHS Avian',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Gudang',
        ]);
    }

    /** Item ala laporan stok WHS: membawa kolom stok. */
    private function item(string $noPart, float $saldo, float $fisik = 0, array $stok = []): array
    {
        return [
            'noPart' => $noPart, 'sparepart' => 'Part ' . $noPart,
            'saldoAkhir' => $saldo, 'fisik' => $fisik, 'wo' => 0,
            'akhir' => $saldo - $fisik, 'selisih' => $fisik - $saldo,
            'keterangan' => '', 'tgl' => '2026-09-21', 'logScan' => [],
            'stok' => $stok,
        ];
    }

    private function isiDaftar(array $items, ?PlanAudit $plan = null): PemeriksaanHgp
    {
        return PemeriksaanHgp::query()->create([
            'plan_audit_id' => ($plan ?? $this->plan)->id,
            'items_json'    => $items,
        ]);
    }

    private function saklar(bool $aktif, ?PlanAudit $plan = null)
    {
        return $this->postJson('/api/audit-detail/hgp/hitung-fkt-claim', [
            'plan_audit_id' => ($plan ?? $this->plan)->id,
            'aktif'         => $aktif,
        ]);
    }

    private function baca(?PlanAudit $plan = null): array
    {
        return $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . ($plan ?? $this->plan)->id)
            ->json('data.items');
    }

    public function test_bawaannya_mati_dan_fkt_claim_tidak_mengubah_apa_pun(): void
    {
        $this->isiDaftar([
            $this->item('P1', saldo: 10, fisik: 10, stok: ['fakturBelumKutip' => 3, 'claim' => 2]),
        ]);

        $it = $this->baca()[0];

        $this->assertEquals(0, $it['akhir'], 'tanpa saklar, FKT & Claim tidak dihitung');
        $this->assertEquals(0, $it['selisih']);
    }

    public function test_dinyalakan_fkt_mengurangi_saldo_dan_claim_menambah_fisik(): void
    {
        $this->isiDaftar([
            $this->item('P1', saldo: 10, fisik: 4, stok: ['fakturBelumKutip' => 3, 'claim' => 2]),
        ]);

        $this->saklar(true)->assertOk()->assertJson(['hitungFktClaim' => true, 'terdampak' => 1]);

        $it = $this->baca()[0];

        // saldo 10 - FKT 3 = 7 ; fisik 4 + Claim 2 = 6 ; akhir 7-6 = 1
        $this->assertEquals(1, $it['akhir']);
        $this->assertEquals(-1, $it['selisih']);
    }

    public function test_hasil_scan_dan_titipan_tidak_disentuh_saat_saklar_diubah(): void
    {
        $awal = $this->item('P1', saldo: 10, fisik: 4, stok: ['fakturBelumKutip' => 3]);
        $awal['wo'] = 2;
        $awal['keterangan'] = 'rak B3';
        $awal['logScan'] = [['id' => 'x1', 'at' => '2026-09-21T08:00:00+07:00', 'qty' => 4]];

        $rec = $this->isiDaftar([$awal]);

        $this->saklar(true)->assertOk();

        $it = $rec->fresh()->items_json[0];

        $this->assertEquals(4, $it['fisik'], 'fisik hasil scan tidak boleh berubah');
        $this->assertEquals(2, $it['wo'], 'Titipan tidak boleh berubah');
        $this->assertSame('rak B3', $it['keterangan']);
        $this->assertCount(1, $it['logScan']);
        $this->assertEquals(10, $it['saldoAkhir'], 'saldo mentah dari berkas tetap utuh');
    }

    public function test_bisa_dimatikan_lagi_dan_angkanya_kembali_seperti_semula(): void
    {
        $rec = $this->isiDaftar([
            $this->item('P1', saldo: 10, fisik: 4, stok: ['fakturBelumKutip' => 3, 'claim' => 2]),
        ]);

        $this->saklar(true)->assertOk();
        $this->assertEquals(1, $rec->fresh()->items_json[0]['akhir']);

        $this->saklar(false)->assertOk()->assertJson(['hitungFktClaim' => false]);

        $it = $rec->fresh()->items_json[0];
        $this->assertEquals(6, $it['akhir'], 'kembali ke 10 - 4');
        $this->assertEquals(-6, $it['selisih']);
    }

    public function test_saldo_boleh_minus_karena_itu_konsekuensi_aturannya(): void
    {
        // Persis pola yang ada di berkas WHS asli: FKT lebih besar dari saldo,
        // karena laporan WHS umumnya sudah memotongnya lewat kolom KELUAR.
        $rec = $this->isiDaftar([
            $this->item('082322MBK5LN9', saldo: 191, fisik: 0, stok: ['fakturBelumKutip' => 480]),
        ]);

        $this->saklar(true)->assertOk();

        $this->assertEquals(-289, $rec->fresh()->items_json[0]['akhir']);
    }

    public function test_plan_lain_tidak_ikut_terpengaruh(): void
    {
        $lain = PlanAudit::query()->create([
            'no_spt' => '0501/01/01/2026/SPT-IAT', 'cabang' => 'WHS Bravo',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);
        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $lain->id, 'tool' => 'hgp',
            'nama_auditor' => 'A', 'nama_auditee' => 'B',
        ]);

        $this->isiDaftar([$this->item('P1', saldo: 10, fisik: 4, stok: ['fakturBelumKutip' => 3])]);
        $recLain = $this->isiDaftar([$this->item('P1', saldo: 10, fisik: 4, stok: ['fakturBelumKutip' => 3])], $lain);

        $this->saklar(true)->assertOk();

        $this->assertEquals(3, $this->baca()[0]['akhir'], 'plan ini ikut aturan');
        $this->assertEquals(6, $this->baca($lain)[0]['akhir'], 'plan lain tidak');
        $this->assertFalse((bool) $recLain->fresh()->hitung_fkt_claim);
    }

    public function test_berkas_onhand_cabang_tanpa_kolom_stok_tidak_terpengaruh(): void
    {
        // Berkas cabang tidak punya kolom FKT/Claim sama sekali.
        $rec = $this->isiDaftar([$this->item('P1', saldo: 10, fisik: 4, stok: [])]);

        $this->saklar(true)->assertOk()->assertJson(['terdampak' => 0]);

        $this->assertEquals(6, $rec->fresh()->items_json[0]['akhir']);
    }

    public function test_scan_berikutnya_ikut_aturan_yang_sedang_berlaku(): void
    {
        $rec = $this->isiDaftar([
            $this->item('P1', saldo: 10, fisik: 0, stok: ['fakturBelumKutip' => 3, 'claim' => 2]),
        ]);

        $this->saklar(true)->assertOk();

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'plan_audit_id' => $this->plan->id,
            'noPart'        => 'P1',
            'qty'           => 0,
            'wo'            => 1,
        ])->assertOk();

        // saldo 10-3 = 7 ; fisik 0 + claim 2 + wo 1 = 3 ; akhir 4
        $this->assertEquals(4, $rec->fresh()->items_json[0]['akhir']);
    }

    public function test_export_selisih_ikut_aturan_yang_menyala(): void
    {
        $rec = $this->isiDaftar([
            // Tanpa aturan selisihnya 0 -> tidak ikut export.
            // Dengan aturan: saldo 7, fisik 10 -> selisih +3 -> ikut export.
            $this->item('P1', saldo: 10, fisik: 10, stok: ['fakturBelumKutip' => 3]),
        ]);

        $tanpa = $this->get('/api/audit-detail/hgp/export-selisih?plan_audit_id=' . $this->plan->id);
        $tanpa->assertOk();
        $this->assertStringNotContainsString('P1', $this->isiXlsx($tanpa));

        $this->saklar(true)->assertOk();

        $dengan = $this->get('/api/audit-detail/hgp/export-selisih?plan_audit_id=' . $this->plan->id);
        $dengan->assertOk();
        $this->assertStringContainsString('P1', $this->isiXlsx($dengan));
    }

    private function isiXlsx($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'selisih') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        $teks = '';
        foreach (\PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getAllSheets() as $sheet) {
            foreach ($sheet->toArray() as $baris) {
                $teks .= implode('|', array_map(fn ($c) => (string) $c, $baris)) . "\n";
            }
        }

        return $teks;
    }

    public function test_ditolak_kalau_daftar_item_belum_ada(): void
    {
        $this->saklar(true)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Daftar item belum ada. Import data HGP & AHM Oils dulu.']);
    }
}
