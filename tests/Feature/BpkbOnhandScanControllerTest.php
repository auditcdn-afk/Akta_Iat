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

    public function test_nomor_yang_memuat_spasi_dan_huruf_di_belakang_ditemukan(): void
    {
        // Dilaporkan dari lapangan: nomor seperti ini dipilih dari daftar
        // saran, tapi scan-nya tetap "tidak ditemukan" -- layar memangkasnya
        // menjadi "I-04308002" sebelum dikirim, padahal " D" bagian nomornya.
        BpkbOnhandItem::create([
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'I-04308002 D',
            'nama_pemilik'  => 'ARISMAN',
            'jenis'         => 'REG',
        ]);

        $res = $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'I-04308002 D',
        ])->assertOk();

        $this->assertSame('found', $res->json('status'));
        $this->assertSame('I-04308002 D', $res->json('data.noBpkb'));
        $this->assertDatabaseHas('bpkb_onhand_items', ['no_bpkb' => 'I-04308002 D', 'sudah_scan' => true]);
    }

    public function test_embel_embel_hasil_scan_barcode_tetap_dikenali(): void
    {
        // Alat scan ikut membaca tulisan lain di lembar BPKB. Pemangkasan itu
        // sekarang pekerjaan server, sesudah nomor apa adanya dicoba dulu.
        $res = $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'Q-07856595-BPKB POLRI 2025',
        ])->assertOk();

        $this->assertSame('found', $res->json('status'));
        $this->assertDatabaseHas('bpkb_onhand_items', ['no_bpkb' => 'Q-07856595', 'sudah_scan' => true]);
        $this->assertSame(1, BpkbOnhandItem::where('plan_audit_id', $this->plan->id)->count());
    }

    public function test_baris_diluar_onhand_dicatat_tanpa_tulisan_lain_dari_alat_scan(): void
    {
        $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'W1840506-BPKB POLRI 2025',
            'force'         => true,
        ])->assertOk()->assertJsonPath('status', 'outside');

        $this->assertDatabaseHas('bpkb_onhand_items', ['no_bpkb' => 'W1840506', 'jenis' => 'LUAR']);
    }

    public function test_huruf_pendek_di_belakang_nomor_ikut_tersimpan_saat_dicatat_diluar_onhand(): void
    {
        // Kalau " D" ikut dibuang, dua BPKB yang berbeda akan tercatat dengan
        // nomor yang sama.
        $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'I-04308002 D',
            'force'         => true,
        ])->assertOk()->assertJsonPath('status', 'outside');

        $this->assertDatabaseHas('bpkb_onhand_items', ['no_bpkb' => 'I-04308002 D', 'jenis' => 'LUAR']);
    }

    public function test_nomor_tanpa_huruf_belakang_tidak_menandai_bpkb_lain_yang_berhuruf(): void
    {
        BpkbOnhandItem::create([
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'I-04308002 D',
            'jenis'         => 'REG',
        ]);

        $this->postJson('/api/audit-detail/bpkb/scan', [
            'plan_audit_id' => $this->plan->id,
            'no_bpkb'       => 'I-04308002',
        ])->assertOk()->assertJsonPath('status', 'confirm');

        $this->assertDatabaseHas('bpkb_onhand_items', ['no_bpkb' => 'I-04308002 D', 'sudah_scan' => false]);
    }

    public function test_layar_tidak_lagi_memangkas_nomor_sebelum_dikirim(): void
    {
        $js = file_get_contents(resource_path('js/audit-editor.js'));

        // Pemangkas di sisi layar itulah yang membuat nomor bernspasi tidak
        // pernah sampai utuh ke server.
        $this->assertStringNotContainsString('bpkbExtractNoBpkb', $js);
    }
}
