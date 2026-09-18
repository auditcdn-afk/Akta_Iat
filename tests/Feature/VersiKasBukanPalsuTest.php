<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Penjaga "jangan timpa versi yang belum dilihat" hanya boleh menyala kalau
 * server memang sudah lebih baru. Kalau ia menyala pada layar yang justru
 * BARU SAJA memuat datanya, auditor jadi tidak bisa menyimpan apa pun —
 * peringatannya benar bunyinya tapi salah sasaran.
 *
 * Yang diuji di sini persis rantai yang dipakai peramban: versi diambil dari
 * respons API, lalu dikirim balik apa adanya pada simpan berikutnya.
 */
class VersiKasBukanPalsuTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'SO UJT',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'kas',
            'nama_auditor'  => 'AUDITOR SATU',
            'nama_auditee'  => 'AUDITEE SATU',
        ]);
    }

    private function isiKas(array $ubah = []): array
    {
        return array_merge([
            'plan_audit_id' => $this->plan->id,
            'nama_pos'      => 'Pemeriksaan Kas',
            'saldo_fisik'   => 1000000,
            'saldo_buku'    => 1000000,
            'detail_json'   => ['kas_besar' => ['saldo_awal' => 1000000]],
        ], $ubah);
    }

    /**
     * Versi yang dipakai peramban datang dari respons simpan. Dikirim kembali
     * apa adanya, ia harus dikenali sebagai versi yang sama.
     */
    public function test_versi_dari_respons_simpan_tidak_ditolak(): void
    {
        $buat = $this->postJson("/api/audit-detail/kas", $this->isiKas())->assertCreated();
        $versi = $buat->json('data.updated_at');
        $id    = $buat->json('data.id');

        $this->assertNotNull($versi, 'Respons simpan harus membawa waktu perubahan.');

        $this->putJson("/api/audit-detail/kas/{$id}", $this->isiKas([
            'versi'       => $versi,
            'saldo_fisik' => 1500000,
        ]))->assertOk();
    }

    /** Versi dari respons BACA (saat tab dimuat) juga harus dikenali. */
    public function test_versi_dari_respons_baca_tidak_ditolak(): void
    {
        $id = $this->postJson("/api/audit-detail/kas", $this->isiKas())->assertCreated()->json('data.id');

        $baca  = $this->getJson("/api/audit-detail/kas?plan_audit_id={$this->plan->id}")->assertOk();
        $versi = $baca->json('data.0.updated_at') ?? $baca->json('0.updated_at');
        $this->assertNotNull($versi, 'Respons baca harus membawa waktu perubahan.');

        $this->putJson("/api/audit-detail/kas/{$id}", $this->isiKas([
            'versi'       => $versi,
            'saldo_fisik' => 1750000,
        ]))->assertOk();
    }

    /** Yang MEMANG basi tetap harus ditolak — penjaganya tidak boleh ikut mati. */
    public function test_versi_yang_benar_benar_basi_tetap_ditolak(): void
    {
        $buat  = $this->postJson("/api/audit-detail/kas", $this->isiKas())->assertCreated();
        $id    = $buat->json('data.id');
        $versi = $buat->json('data.updated_at');

        // Rekan auditor menyimpan lebih dulu.
        $this->travel(2)->seconds();
        $this->putJson("/api/audit-detail/kas/{$id}", $this->isiKas(['saldo_fisik' => 2000000]))->assertOk();

        // Layar lama masih memegang versi sebelumnya.
        $this->putJson("/api/audit-detail/kas/{$id}", $this->isiKas([
            'versi'       => $versi,
            'saldo_fisik' => 3000000,
        ]))->assertStatus(409)->assertJson(['stale' => true]);
    }
}
