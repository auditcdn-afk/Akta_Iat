<?php

namespace Tests\Feature;

use App\Models\BpkbOnhandItem;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Scan No BPKB di tab Onhand BPKB: nomor yang cocok dengan data onhand
 * ditandai "fisik ada". Nomor yang TIDAK cocok dulu hanya menawarkan
 * konfirmasi (status "confirm", tidak menulis apa pun) — baru dicatat
 * sebagai "Fisik Diluar On Hand" kalau dikirim ulang dengan force=true.
 * Ini mencegah salah ketik/typo langsung membuat baris baru tanpa disadari.
 */
class BpkbOnhandScanControllerTest extends TestCase
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

        BpkbOnhandItem::create([
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'Q-07856595',
            'jenis'         => 'REG',
        ]);
    }

    public function test_nomor_yang_cocok_langsung_ditandai_fisik_ada(): void
    {
        $response = $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'Q-07856595',
        ]);

        $response->assertOk();
        $this->assertSame('found', $response->json('status'));
        $this->assertDatabaseHas('bpkb_onhand_items', [
            'no_bpkb'     => 'Q-07856595',
            'sudah_scan'  => true,
        ]);
        $this->assertSame(1, BpkbOnhandItem::where('plan_audit_id', $this->plan->id)->count());
    }

    public function test_nomor_tidak_cocok_tanpa_force_hanya_minta_konfirmasi_tidak_menulis(): void
    {
        $response = $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'X-99999999',
        ]);

        $response->assertOk();
        $this->assertSame('confirm', $response->json('status'));
        $this->assertNull($response->json('data'));
        $this->assertSame(1, BpkbOnhandItem::where('plan_audit_id', $this->plan->id)->count());
        $this->assertDatabaseMissing('bpkb_onhand_items', ['no_bpkb' => 'X-99999999']);
    }

    public function test_nomor_tidak_cocok_dengan_force_dicatat_sebagai_fisik_diluar_onhand(): void
    {
        $response = $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'X-99999999',
            'force'         => true,
        ]);

        $response->assertOk();
        $this->assertSame('outside', $response->json('status'));
        $this->assertDatabaseHas('bpkb_onhand_items', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'X-99999999',
            'jenis'         => 'LUAR',
            'sudah_scan'    => true,
        ]);
        $this->assertSame(2, BpkbOnhandItem::where('plan_audit_id', $this->plan->id)->count());
    }
}
