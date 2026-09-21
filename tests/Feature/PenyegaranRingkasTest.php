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
 * Lima auditor mengerjakan satu SPT bersamaan, dan tiap layar menarik keadaan
 * terbaru tiap 20 detik supaya hasil scan rekan ikut terlihat.
 *
 * Dulu yang ditarik SELURUH daftar item -- pada audit gudang WHS isinya 4.781
 * item (±1,3 MB) -- walau tidak ada satu pun scan baru di antaranya. Lima
 * auditor berarti lima kali membaca, menguraikan, dan mengirim 1,3 MB setiap
 * 20 detik, terus-menerus sepanjang pemeriksaan.
 */
class PenyegaranRingkasTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0480/01/09/2026/SPT-IAT', 'cabang' => 'WHS Part KIM',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Gudang',
        ]);
    }

    /** @param array<int, array<string, mixed>> $tambahan */
    private function isiDaftar(int $jumlah, array $tambahan = []): PemeriksaanHgp
    {
        $items = [];

        for ($i = 1; $i <= $jumlah; $i++) {
            $items[] = [
                'noPart' => "PART-{$i}", 'sparepart' => "Sparepart {$i}",
                'saldoAkhir' => 10, 'fisik' => 0, 'akhir' => 10, 'selisih' => -10,
                'keterangan' => '', 'tgl' => '2026-09-21', 'logScan' => [],
            ];
        }

        foreach ($tambahan as $i => $ganti) {
            $items[$i] = array_merge($items[$i], $ganti);
        }

        return PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => $items,
            'created_by'    => 'auditor1',
        ]);
    }

    private function ringkas(?string $versi = null)
    {
        $url = '/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id . '&ringkas=1'
            . ($versi ? '&versi=' . urlencode($versi) : '');

        return $this->getJson($url);
    }

    public function test_tanpa_perubahan_tidak_mengirim_satu_item_pun(): void
    {
        $this->isiDaftar(500);

        // Penyegaran pertama membawa versinya; yang kedua memakai versi itu --
        // persis seperti yang dilakukan layar auditor.
        $versi = $this->ringkas()->assertOk()->json('versi');

        $res = $this->ringkas($versi)->assertOk();

        $this->assertFalse($res->json('berubah'));
        $this->assertNull($res->json('data'));

        // Inilah ukuran yang dulu 1,3 MB pada audit gudang WHS.
        $this->assertLessThan(200, strlen($res->getContent()));
    }

    public function test_hanya_item_yang_sudah_discan_yang_dikirim(): void
    {
        $this->isiDaftar(500, [
            3 => ['fisik' => 2, 'logScan' => [['at' => '2026-09-21T07:00:00Z', 'qty' => 2, 'id' => 'x1']]],
            7 => ['keterangan' => 'Rak B'],
        ]);

        $res = $this->ringkas()->assertOk();

        $this->assertTrue($res->json('berubah'));
        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame('PART-4', $res->json('data.items.0.noPart'));
        $this->assertSame('PART-8', $res->json('data.items.1.noPart'));
    }

    public function test_hasil_scan_rekan_tetap_sampai_ke_layar_lain(): void
    {
        $this->isiDaftar(50);
        $versi = $this->ringkas()->assertOk()->json('versi');

        // Rekan auditor menembak barcode.
        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'plan_audit_id' => $this->plan->id,
            'noPart'        => 'PART-9',
            'entries'       => [['id' => 'scan-1', 'qty' => 3, 'at' => '2026-09-21T07:05:00Z']],
        ])->assertOk();

        $res = $this->ringkas($versi)->assertOk();

        $this->assertTrue($res->json('berubah'));
        $this->assertSame('PART-9', $res->json('data.items.0.noPart'));
        $this->assertEquals(3, $res->json('data.items.0.fisik'));
    }

    public function test_sidik_berubah_saat_daftarnya_diganti_import_ulang(): void
    {
        $rec = $this->isiDaftar(50);

        $sidikAwal = $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->json('data.sidik');

        $this->assertNotEmpty($sidikAwal);

        // Import ulang dengan saldo yang berbeda.
        $items = $rec->items_json;
        $items[0]['saldoAkhir'] = 99;
        $rec->update(['items_json' => $items]);

        $this->assertNotSame($sidikAwal, $this->ringkas()->assertOk()->json('sidik'));
    }

    public function test_sidik_tidak_berubah_karena_scan_biasa(): void
    {
        $rec = $this->isiDaftar(50);

        $sidikAwal = $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->json('data.sidik');

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'plan_audit_id' => $this->plan->id,
            'noPart'        => 'PART-2',
            'entries'       => [['id' => 'scan-2', 'qty' => 1, 'at' => '2026-09-21T07:06:00Z']],
        ])->assertOk();

        // Kalau sidiknya ikut berubah tiap scan, tiap layar akan memuat penuh
        // 1,3 MB setiap kali rekannya menembak satu barcode.
        $this->assertSame($sidikAwal, $this->ringkas()->assertOk()->json('sidik'));
    }

    public function test_permintaan_penuh_tetap_mengirim_seluruh_daftar(): void
    {
        $this->isiDaftar(120);

        $res = $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id)->assertOk();

        $this->assertCount(120, $res->json('data.items'));
    }

    public function test_plan_yang_belum_punya_data_dijawab_tanpa_error(): void
    {
        $this->ringkas()->assertOk()->assertJsonPath('berubah', false);

        $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
