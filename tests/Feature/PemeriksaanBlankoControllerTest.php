<?php

namespace Tests\Feature;

use App\Models\PemeriksaanBlanko;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Register Blanko yang Belum Digunakan (H1/H2) — satu pasang per
 * (plan_audit_id, tool), dipakai di tab SMH & Onhand BPKB.
 */
class PemeriksaanBlankoControllerTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0001/01/01/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);
    }

    public function test_tool_harus_smh_atau_bpkb(): void
    {
        $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'blanko_h1'     => [['jenis' => 'KWT', 'nomor' => 'QB 031615']],
        ])->assertStatus(422);
    }

    public function test_show_mengembalikan_default_kosong_saat_belum_pernah_disimpan(): void
    {
        $response = $this->getJson(
            "/api/audit-detail/blanko?plan_audit_id={$this->plan->id}&tool=smh"
        );

        $response->assertOk();
        $this->assertNull($response->json('data.id'));
        $this->assertSame([], $response->json('data.blankoH1'));
        $this->assertSame([], $response->json('data.blankoH2'));
    }

    public function test_simpan_dan_baca_kembali_untuk_smh(): void
    {
        $response = $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
            'blanko_h1'     => [
                ['jenis' => 'KWT', 'nomor' => 'QB 031615'],
                ['jenis' => 'TTP', 'nomor' => 'ER 007454'],
            ],
            'blanko_h2' => [
                ['jenis' => 'TTS', 'nomor' => 'CA 001613'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pemeriksaan_blankos', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
        ]);

        $show = $this->getJson("/api/audit-detail/blanko?plan_audit_id={$this->plan->id}&tool=smh");
        $show->assertOk();
        $this->assertCount(2, $show->json('data.blankoH1'));
        $this->assertSame('KWT', $show->json('data.blankoH1.0.jenis'));
        $this->assertCount(1, $show->json('data.blankoH2'));
    }

    public function test_smh_dan_bpkb_tersimpan_terpisah_untuk_plan_yang_sama(): void
    {
        $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
            'blanko_h1'     => [['jenis' => 'KWT', 'nomor' => 'SMH-001']],
        ])->assertOk();

        $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'bpkb',
            'blanko_h1'     => [['jenis' => 'KWT', 'nomor' => 'BPKB-001']],
        ])->assertOk();

        $this->assertSame(2, PemeriksaanBlanko::where('plan_audit_id', $this->plan->id)->count());

        $smh = $this->getJson("/api/audit-detail/blanko?plan_audit_id={$this->plan->id}&tool=smh");
        $this->assertSame('SMH-001', $smh->json('data.blankoH1.0.nomor'));

        $bpkb = $this->getJson("/api/audit-detail/blanko?plan_audit_id={$this->plan->id}&tool=bpkb");
        $this->assertSame('BPKB-001', $bpkb->json('data.blankoH1.0.nomor'));
    }

    public function test_menyimpan_ulang_memperbarui_baris_bukan_menambah(): void
    {
        $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
            'blanko_h1'     => [['jenis' => 'KWT', 'nomor' => 'LAMA']],
        ])->assertOk();

        $this->postJson('/api/audit-detail/blanko', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
            'blanko_h1'     => [['jenis' => 'KWT', 'nomor' => 'BARU']],
        ])->assertOk();

        $this->assertSame(1, PemeriksaanBlanko::where('plan_audit_id', $this->plan->id)->where('tool', 'smh')->count());
        $show = $this->getJson("/api/audit-detail/blanko?plan_audit_id={$this->plan->id}&tool=smh");
        $this->assertSame('BARU', $show->json('data.blankoH1.0.nomor'));
    }
}
