<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kolom "WO" di tabel HGP & AHM Oils menambah hitungan fisik, tapi namanya
 * tidak selalu "WO": tergantung cabangnya bisa berarti titipan, display,
 * retur, atau sebutan lain yang dipakai di lapangan.
 *
 * Judulnya karena itu bisa diganti per plan audit. Yang dihitung TIDAK berubah
 * sama sekali — hanya namanya.
 */
class HgpLabelWoTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0500/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Cabang',
        ]);
    }

    private function gantiLabel(?string $label)
    {
        return $this->postJson('/api/audit-detail/hgp/label-wo', [
            'plan_audit_id' => $this->plan->id,
            'label'         => $label,
        ]);
    }

    private function baca()
    {
        return $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $this->plan->id);
    }

    public function test_judul_bawaannya_wo(): void
    {
        PemeriksaanHgp::query()->create(['plan_audit_id' => $this->plan->id, 'items_json' => []]);

        $this->assertSame('WO', $this->baca()->assertOk()->json('data.labelWo'));
    }

    public function test_judul_bisa_diganti(): void
    {
        $this->gantiLabel('Titipan')->assertOk()->assertJsonPath('labelWo', 'Titipan');

        $this->assertSame('Titipan', $this->baca()->assertOk()->json('data.labelWo'));
    }

    public function test_dikosongkan_kembali_ke_judul_bawaan(): void
    {
        $this->gantiLabel('Display')->assertOk();

        $this->gantiLabel('')->assertOk()->assertJsonPath('labelWo', 'WO');

        $this->assertSame('WO', $this->baca()->assertOk()->json('data.labelWo'));
    }

    public function test_mengganti_judul_tidak_menyentuh_hasil_pemeriksaan(): void
    {
        $items = [[
            'noPart' => 'PART-1', 'sparepart' => 'Sparepart Satu',
            'saldoAkhir' => 10, 'fisik' => 4, 'wo' => 2,
            'akhir' => 4, 'selisih' => -4, 'keterangan' => 'Rak A',
            'tgl' => '2026-09-21', 'logScan' => [['at' => '2026-09-21T07:00:00Z', 'qty' => 4, 'id' => 'x1']],
        ]];

        PemeriksaanHgp::query()->create(['plan_audit_id' => $this->plan->id, 'items_json' => $items]);

        $this->gantiLabel('Retur')->assertOk();

        $tersimpan = $this->baca()->assertOk()->json('data.items');

        $this->assertCount(1, $tersimpan);
        $this->assertEquals(4, $tersimpan[0]['fisik']);
        $this->assertEquals(2, $tersimpan[0]['wo']);
        $this->assertCount(1, $tersimpan[0]['logScan']);
    }

    public function test_judul_yang_kepanjangan_ditolak(): void
    {
        $this->gantiLabel(str_repeat('A', 21))->assertStatus(422);
    }

    public function test_judul_menempel_pada_plan_audit_itu_saja(): void
    {
        $lain = PlanAudit::query()->create([
            'no_spt' => '0501/01/01/2026/SPT-IAT', 'cabang' => 'CSC B',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);
        PemeriksaanHgp::query()->create(['plan_audit_id' => $lain->id, 'items_json' => []]);

        $this->gantiLabel('Titipan')->assertOk();

        $this->assertSame(
            'WO',
            $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $lain->id)->assertOk()->json('data.labelWo')
        );
    }

    public function test_judul_ikut_ke_berkas_export_selisih(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [[
                'noPart' => 'PART-1', 'sparepart' => 'Sparepart Satu',
                'saldoAkhir' => 10, 'fisik' => 4, 'wo' => 0,
                'tgl' => '2026-09-21', 'logScan' => [],
            ]],
        ]);

        $this->gantiLabel('Titipan')->assertOk();

        $isi = $this->get('/api/audit-detail/hgp/export-selisih?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->streamedContent();

        $berkas = tempnam(sys_get_temp_dir(), 'selisih') . '.xlsx';
        file_put_contents($berkas, $isi);

        $judul = [];
        foreach (\PhpOffice\PhpSpreadsheet\IOFactory::load($berkas)->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, true, false, false) as $baris) {
                foreach ($baris as $sel) {
                    $judul[] = trim((string) $sel);
                }
            }
        }

        $this->assertContains('Titipan', $judul);
        $this->assertNotContains('WO', $judul);
    }

    public function test_struktur_database_belum_diperbarui_dijawab_terbaca(): void
    {
        Schema::table('pemeriksaan_hgp', fn($t) => $t->dropColumn('label_wo'));

        $this->gantiLabel('Titipan')
            ->assertStatus(422)
            ->assertJsonPath('message', fn($p) => str_contains((string) $p, '/deploy/migrate'));
    }
}
