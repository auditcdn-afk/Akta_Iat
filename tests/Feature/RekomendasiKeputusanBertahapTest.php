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

    // ── Membetulkan isian sendiri ───────────────────────────────────────────

    public function test_pemilik_bisa_membetulkan_isiannya_selama_bagian_berikutnya_belum_mengisi(): void
    {
        $rek = $this->rekomendasi();
        $whs = User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']);

        Sanctum::actingAs($whs);
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok + 4 botol dimusnahkan.')
            ->assertOk()
            ->assertJsonPath('message', 'Keputusan berhasil diperbarui.');

        $this->assertSame(
            'Penyesuaian stok + 4 botol dimusnahkan.',
            $rek->fresh()->steps[self::STEP_WHS]['note']
        );
    }

    public function test_isian_terkunci_begitu_bagian_berikutnya_mengisi(): void
    {
        $rek = $this->rekomendasi();
        $whs = User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']);

        Sanctum::actingAs($whs);
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        // FIN REG mengisi bagiannya -> keputusan WHS jadi dasar pertimbangannya
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'unit_usaha' => 'FIN REG']));
        $this->isiStep($rek, self::STEP_FIN_REG, 'Disetujui.')->assertOk();

        Sanctum::actingAs($whs);
        $res = $this->isiStep($rek, self::STEP_WHS, 'Diubah diam-diam.')->assertStatus(422);
        $this->assertStringContainsString('terkunci', (string) $res->json('message'));

        $this->assertSame('Penyesuaian stok.', $rek->fresh()->steps[self::STEP_WHS]['note']);
    }

    public function test_admin_tetap_bisa_membetulkan_bagian_yang_sudah_terkunci(): void
    {
        $rek = $this->rekomendasi();

        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'unit_usaha' => 'FIN REG']));
        $this->isiStep($rek, self::STEP_FIN_REG, 'Disetujui.')->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));
        $this->isiStep($rek, self::STEP_WHS, 'Dibetulkan admin.')->assertOk();

        $this->assertSame('Dibetulkan admin.', $rek->fresh()->steps[self::STEP_WHS]['note']);
    }

    public function test_pihak_lain_tetap_tidak_bisa_mengubah_isian_orang(): void
    {
        $rek = $this->rekomendasi();

        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        // Auditor bukan pemilik step ini -- ditolak di pemeriksaan kepemilikan,
        // sebelum aturan boleh-ubah sempat dinilai.
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']));
        $this->isiStep($rek, self::STEP_WHS, 'Diubah auditor.')->assertStatus(403);

        $this->assertSame('Penyesuaian stok.', $rek->fresh()->steps[self::STEP_WHS]['note']);
    }

    public function test_daftar_menandai_isian_mana_yang_masih_bisa_dibetulkan(): void
    {
        $rek = $this->rekomendasi();
        $whs = User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']);

        Sanctum::actingAs($whs);
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()->json('data.0.steps');
        $this->assertTrue($steps[self::STEP_WHS]['bisaDiubah'], 'masih boleh dibetulkan');

        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'unit_usaha' => 'FIN REG']));
        $this->isiStep($rek, self::STEP_FIN_REG, 'Disetujui.')->assertOk();

        Sanctum::actingAs($whs);
        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()->json('data.0.steps');
        $this->assertFalse($steps[self::STEP_WHS]['bisaDiubah'], 'sudah terkunci');
        $this->assertFalse($steps[self::STEP_FIN_REG]['bisaDiubah'], 'bukan miliknya');
    }

    public function test_step_yang_belum_diisi_tidak_ditandai_bisa_diubah(): void
    {
        $this->rekomendasi();
        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)
            ->assertOk()->json('data.0.steps');

        $this->assertFalse($steps[self::STEP_WHS]['bisaDiubah'], 'belum diisi, jadi belum ada yang diubah');
        $this->assertTrue($steps[self::STEP_WHS]['bisaDiisi']);
    }

    public function test_seluruh_rekomendasi_disetujui_mengunci_semua_isian(): void
    {
        $rek = $this->rekomendasi();

        foreach ([
            [self::STEP_WHS, 'whs', 'WHS Unit ARK'],
            [self::STEP_FIN_REG, 'viewer', 'FIN REG'],
            [3, 'viewer', 'REG HEAD'],
            [self::STEP_MANAJER, 'manajer', ''],
            [self::STEP_AFD, 'afd', ''],
        ] as [$idx, $role, $unit]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));
            $this->isiStep($rek, $idx, 'ok')->assertOk();
        }

        $this->assertSame('approved', $rek->fresh()->status);

        Sanctum::actingAs(User::factory()->create(['role' => 'afd', 'unit_usaha' => '']));
        $this->isiStep($rek, self::STEP_AFD, 'diubah')->assertStatus(422);
    }

    public function test_keputusan_terakhir_terkunci_begitu_diisi_dan_hanya_admin_yang_bisa_mengubah(): void
    {
        $rek = $this->rekomendasi();

        foreach ([
            [self::STEP_WHS, 'whs', 'WHS Unit ARK'],
            [self::STEP_FIN_REG, 'viewer', 'FIN REG'],
            [3, 'viewer', 'REG HEAD'],
            [self::STEP_MANAJER, 'manajer', ''],
        ] as [$idx, $role, $unit]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));
            $this->isiStep($rek, $idx, 'ok')->assertOk();
        }

        // AFD menjatuhkan keputusan penutup...
        $afd = User::factory()->create(['role' => 'afd', 'unit_usaha' => '']);
        Sanctum::actingAs($afd);
        $this->isiStep($rek, self::STEP_AFD, 'Disetujui.')->assertOk();

        // ...dan tidak bisa mengubahnya sendiri lagi: tidak ada bagian
        // berikutnya yang bisa jadi penanda, dan keputusan penutup memang tidak
        // semestinya bisa diubah sendiri sesudah dijatuhkan.
        $this->isiStep($rek, self::STEP_AFD, 'Dibatalkan diam-diam.')->assertStatus(422);
        $this->assertSame('Disetujui.', $rek->fresh()->steps[self::STEP_AFD]['note']);

        $steps = $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)->json('data.0.steps');
        $this->assertFalse($steps[self::STEP_AFD]['bisaDiubah'], 'tombol Edit tidak boleh muncul untuk AFD');

        // Admin tetap bisa membetulkan.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));
        $this->isiStep($rek, self::STEP_AFD, 'Dibetulkan admin.')->assertOk();
        $this->assertSame('Dibetulkan admin.', $rek->fresh()->steps[self::STEP_AFD]['note']);
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
        ] as [$role, $unit, $harapan]) {   // rekomendasi masih kosong: auditor boleh
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

    public function test_auditor_tidak_bisa_menghapus_setelah_ada_yang_mengisi(): void
    {
        $rek = $this->rekomendasi();

        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']));
        $res = $this->deleteJson("/api/recommendations/{$rek->id}")->assertStatus(422);
        $this->assertStringContainsString('sudah diisi pihak lain', (string) $res->json('message'));

        $this->assertNotNull(AuditRecommendation::find($rek->id), 'isian WHS tidak boleh ikut terhapus');

        // Admin tetap bisa
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));
        $this->deleteJson("/api/recommendations/{$rek->id}")->assertOk();
        $this->assertNull(AuditRecommendation::find($rek->id));
    }

    public function test_daftar_menandai_kapan_rekomendasi_masih_bisa_dihapus(): void
    {
        $rek     = $this->rekomendasi();
        $auditor = User::factory()->create(['role' => 'auditor', 'unit_usaha' => '']);

        Sanctum::actingAs($auditor);
        $this->assertTrue(
            $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)->json('data.0.bisaDihapus'),
            'belum ada yang mengisi'
        );

        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit ARK']));
        $this->isiStep($rek, self::STEP_WHS, 'Penyesuaian stok.')->assertOk();

        Sanctum::actingAs($auditor);
        $this->assertFalse(
            $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)->json('data.0.bisaDihapus'),
            'sudah ada yang mengisi'
        );
    }

    // ── Isian Unit Usaha ────────────────────────────────────────────────────

    private function isianUnitUsaha(AuditRecommendation $rek)
    {
        return $this->postJson("/api/recommendations/{$rek->id}/isi", [
            'tgl_isi' => '2026-09-25',
            'isi'     => 'Sudah kami tindak lanjuti.',
        ]);
    }

    public function test_isian_unit_usaha_hanya_untuk_unit_yang_diperiksa(): void
    {
        $rek = $this->rekomendasi();

        // Auditor & manajer bukan pihaknya -- ini tanggapan cabang atas
        // rekomendasi auditor, bukan tulisan auditor sendiri.
        foreach (['auditor', 'manajer'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => 'AUDIT']));
            $res = $this->isianUnitUsaha($rek)->assertStatus(403);
            $this->assertStringContainsString('unit usaha yang diperiksa', (string) $res->json('message'));
        }

        // Unit usaha lain juga tidak
        Sanctum::actingAs(User::factory()->create(['role' => 'whs', 'unit_usaha' => 'WHS Unit AKS']));
        $this->isianUnitUsaha($rek)->assertStatus(403);

        $this->assertCount(0, array_filter($rek->fresh()->steps, fn ($s) => ($s['step'] ?? '') === 'isi_rekomendasi'));
    }

    public function test_unit_usaha_yang_diperiksa_dan_admin_tetap_bisa_mengisi(): void
    {
        foreach ([
            ['whs', 'WHS Unit ARK'],
            ['admin', 'HO'],
        ] as [$role, $unit]) {
            $rek = $this->rekomendasi();
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));

            $this->isianUnitUsaha($rek)->assertOk();

            $this->assertCount(
                1,
                array_filter($rek->fresh()->steps, fn ($s) => ($s['step'] ?? '') === 'isi_rekomendasi'),
                "role={$role}"
            );
        }
    }

    public function test_daftar_menandai_siapa_yang_boleh_menulis_isian_unit_usaha(): void
    {
        $this->rekomendasi();

        foreach ([
            ['whs', 'WHS Unit ARK', true],
            ['admin', 'HO', true],
            ['auditor', 'AUDIT', false],
            ['manajer', '', false],
            ['viewer', 'FIN REG', false],
        ] as [$role, $unit, $harapan]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'unit_usaha' => $unit]));

            $this->assertSame(
                $harapan,
                $this->getJson('/api/recommendations?plan_audit_id=' . $this->plan->id)->json('data.0.bisaIsiUnitUsaha'),
                "role={$role} unit={$unit}"
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
