<?php

namespace Tests\Feature;

use App\Models\AuditRecommendation;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\BirokrasiResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Keputusan Bertahap: tiap pihak menuliskan keputusannya SENDIRI.
 *
 * Dilaporkan dari lapangan: auditor bisa mengisi keputusan milik pihak lain.
 * Penyebabnya otorisasi step punya aturan sendiri -- admin/manajer/auditor
 * boleh mengisi step apa pun kecuali "AFD" -- sementara layar tidak memeriksa
 * peran sama sekali, jadi tombol "Isi Keputusan" muncul untuk siapa saja yang
 * kebetulan gilirannya sudah tiba.
 */
class RekomendasiKeputusanBertahapTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    /** Urutan step untuk WHS Unit ARK: created → WHS → FIN REG → REG HEAD → Manajer Audit → AFD */
    private const STEP_WHS      = 1;
    private const STEP_FIN_REG  = 2;
    private const STEP_MANAJER  = 4;
    private const STEP_AFD      = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0461/01/09/2026/SPT-IAT',
            'cabang'      => 'WHS Unit ARK',
            'jenis_audit' => 'Audit Full WHS',
            'status'      => 'running',
        ]);
    }

    private function rekomendasi(): AuditRecommendation
    {
        return AuditRecommendation::query()->create([
            'plan_audit_id' => $this->plan->id,
            'judul'         => 'Selisih HGP & AHM Oil',
            'status'        => 'open',
            'prioritas'     => 'sedang',
            'steps'         => BirokrasiResolver::buildSteps('WHS Unit ARK', 'auditor1'),
            'created_by'    => 'auditor1',
        ]);
    }

    private function isiStep(AuditRecommendation $rek, int $idx, string $catatan = 'keputusan')
    {
        return $this->postJson("/api/recommendations/{$rek->id}/approve-step", [
            'step_index' => $idx,
            'note'       => $catatan,
        ]);
    }

    public function test_urutan_stepnya_seperti_yang_diuji(): void
    {
        $steps = $this->rekomendasi()->steps;

        $this->assertSame('WHS', $steps[self::STEP_WHS]['step']);
        $this->assertSame('FIN REG', $steps[self::STEP_FIN_REG]['step']);
        $this->assertSame('Manajer Audit', $steps[self::STEP_MANAJER]['step']);
        $this->assertSame('AFD', $steps[self::STEP_AFD]['step']);
    }

    // ── Auditor tidak boleh mengisi keputusan pihak lain ────────────────────

    public function test_auditor_tidak_bisa_mengisi_keputusan_pihak_lain(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']));

        $res = $this->isiStep($rek, self::STEP_WHS)->assertStatus(403);
        $this->assertStringContainsString('pihak yang bersangkutan', (string) $res->json('message'));

        $this->isiStep($rek, self::STEP_FIN_REG)->assertStatus(403);

        $this->assertSame('pending', $rek->fresh()->steps[self::STEP_WHS]['status']);
    }

    public function test_manajer_tidak_bisa_mengisi_keputusan_unit_usaha(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'manajer', 'unit_usaha' => '']));

        $this->isiStep($rek, self::STEP_WHS)->assertStatus(403);
        $this->assertSame('pending', $rek->fresh()->steps[self::STEP_WHS]['status']);
    }

    public function test_manajer_tetap_bisa_mengisi_stepnya_sendiri(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'manajer', 'unit_usaha' => '']));

        $this->isiStep($rek, self::STEP_MANAJER, 'disetujui manajer')->assertOk();

        $this->assertSame('done', $rek->fresh()->steps[self::STEP_MANAJER]['status']);
    }

    public function test_admin_tetap_bisa_menimpa_step_mana_pun(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));

        $this->isiStep($rek, self::STEP_FIN_REG, 'diisi admin')->assertOk();

        $this->assertSame('done', $rek->fresh()->steps[self::STEP_FIN_REG]['status']);
    }

    // ── Pihak yang bersangkutan tetap bisa ──────────────────────────────────

    public function test_pihak_yang_bersangkutan_tetap_bisa_mengisi(): void
    {
        foreach ([
            ['whs', '', self::STEP_WHS],                        // cocok lewat role
            ['viewer', 'FIN REG', self::STEP_FIN_REG],          // cocok lewat unit usaha
            ['viewer', 'WHS Unit ARK', self::STEP_WHS],         // step generik jenis unit
            ['afd', '', self::STEP_AFD],
        ] as [$role, $unit, $idx]) {
            $rek = $this->rekomendasi();
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));

            $this->isiStep($rek, $idx)->assertOk();
            $this->assertSame('done', $rek->fresh()->steps[$idx]['status'], "role={$role} unit={$unit} idx={$idx}");
        }
    }

    // ── Penanda untuk layar ─────────────────────────────────────────────────

    public function test_daftar_menandai_step_mana_yang_boleh_diisi_pengguna_ini(): void
    {
        $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->json('data.0.steps');

        $this->assertTrue($steps[self::STEP_WHS]['bisaDiisi'], 'step WHS miliknya');
        $this->assertFalse($steps[self::STEP_FIN_REG]['bisaDiisi'], 'FIN REG bukan miliknya');
        $this->assertFalse($steps[self::STEP_AFD]['bisaDiisi'], 'AFD bukan miliknya');
    }

    public function test_auditor_tidak_ditandai_boleh_mengisi_step_pihak_lain(): void
    {
        $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']));

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->json('data.0.steps');

        foreach ([self::STEP_WHS, self::STEP_FIN_REG, self::STEP_MANAJER, self::STEP_AFD] as $idx) {
            $this->assertFalse($steps[$idx]['bisaDiisi'], "step index {$idx} tidak boleh ditawarkan ke auditor");
        }
    }

    public function test_admin_ditandai_boleh_mengisi_semua_step(): void
    {
        $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()
            ->json('data.0.steps');

        foreach ([self::STEP_WHS, self::STEP_FIN_REG, self::STEP_AFD] as $idx) {
            $this->assertTrue($steps[$idx]['bisaDiisi'], "admin harus bisa menimpa step index {$idx}");
        }
    }

    // ── Hapus rekomendasi ───────────────────────────────────────────────────

    public function test_hapus_hanya_untuk_auditor_dan_admin(): void
    {
        foreach ([
            ['admin', 'HO', 200],
            ['auditor', '', 200],
            ['manajer', '', 403],
            ['whs', 'WHS Unit ARK', 403],
            ['viewer', 'FIN REG', 403],
        ] as [$role, $unit, $harapan]) {
            $rek = $this->rekomendasi();
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));

            $this->deleteJson("/api/recommendations/{$rek->id}")->assertStatus($harapan);

            $this->assertSame(
                $harapan === 200 ? null : $rek->id,
                AuditRecommendation::find($rek->id)?->id,
                "hapus oleh role {$role}"
            );
        }
    }

    public function test_edit_tetap_hanya_admin(): void
    {
        $rek = $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']));

        $this->putJson("/api/recommendations/{$rek->id}", ['judul' => 'DIUBAH'])->assertStatus(403);
        $this->assertSame('Selisih HGP & AHM Oil', $rek->fresh()->judul);
    }
}
