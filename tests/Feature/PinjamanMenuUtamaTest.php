<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PinjamanCabang;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\PlanTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menu utama "Pinjaman BPK & BPB" — daftar pengajuan lintas plan.
 *
 * Sebelum ini pinjaman HANYA hidup di dalam modal halaman Task, dan itu
 * menimbulkan dua lubang:
 *
 * 1. Halaman Task menyaring tugas untuk Koordinator/Manajer/COO berdasarkan
 *    status PLAN-nya, bukan status pengajuannya. Pengajuan yang menunggu
 *    Koordinator pada plan yang sudah berjalan karena itu tidak pernah muncul
 *    di layar Koordinator sama sekali.
 * 2. Endpoint approve tidak pernah memeriksa giliran — siapa pun yang lolos
 *    middleware rutenya bisa menyetujui pengajuan pada tahap mana pun,
 *    melompati seluruh tahap di antaranya. Tombolnya memang disembunyikan di
 *    browser, tapi itu tampilan, bukan kewenangan.
 *
 * Kewenangan lihatnya dua tingkat: admin, manajer, auditor, dan COO melihat
 * seluruh pengajuan; koordinator, unit, dan bpk hanya yang menjadi
 * birokrasinya sendiri.
 */
class PinjamanMenuUtamaTest extends TestCase
{
    use RefreshDatabase;

    private AuditTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = PlanAudit::query()->create([
            'no_spt'      => '0501/12/09/2026/SPT-IAT',
            'cabang'      => 'CSC LANGSA',
            'jenis_audit' => 'Audit Full CSC',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => [],
            'status'      => 'running',
            'created_by'  => 'aziz',
        ]);

        app(PlanTaskService::class)->syncPlan($plan);
        $this->task = AuditTask::where('plan_audit_id', $plan->id)->firstOrFail();
    }

    private function pinjaman(string $status, array $ganti = []): PinjamanCabang
    {
        return PinjamanCabang::query()->create([
            'audit_task_id'    => $this->task->id,
            'jenis'            => 'BPK',
            'cabang_realisasi' => ['SO ARK'],
            'no_spd'           => '6705/CDN/TR/09/26',
            'nominal'          => 5000000,
            'departemen'       => 'Finance',
            'status'           => $status,
            'approvals'        => [['role' => 'auditor', 'user' => 'aziz', 'action' => 'submit', 'at' => now()->toDateTimeString()]],
            'created_by'       => 'aziz',
            ...$ganti,
        ]);
    }

    private function daftar(string $role, array $ganti = []): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, ...$ganti]));

        return $this->getJson('/api/pinjaman-cabang/daftar')->assertOk()->json();
    }

    // ── Kewenangan lihat ─────────────────────────────────────────────────────

    /** @return array<string,array{0:string}> */
    public static function rolePengawas(): array
    {
        return ['admin' => ['admin'], 'manajer' => ['manajer'], 'auditor' => ['auditor'], 'coo' => ['coo']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolePengawas')]
    public function test_role_pengawas_melihat_seluruh_pengajuan(string $role): void
    {
        $this->pinjaman('pending_koordinator');
        $this->pinjaman('pending_bpk', ['jenis' => 'BPB']);
        $this->pinjaman('approved');

        $res = $this->daftar($role);

        $this->assertTrue($res['bolehLihatSemua']);
        $this->assertCount(3, $res['data']);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function roleTerbatas(): array
    {
        return [
            'koordinator' => ['koordinator', 'pending_koordinator'],
            'unit'        => ['unit', 'pending_unit'],
            'bpk'         => ['bpk', 'pending_bpk'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roleTerbatas')]
    public function test_role_birokrasi_hanya_melihat_gilirannya_sendiri(string $role, string $tahap): void
    {
        $milikSaya = $this->pinjaman($tahap);
        $this->pinjaman('approved');
        $this->pinjaman('rejected');

        $res = $this->daftar($role);

        $this->assertFalse($res['bolehLihatSemua']);
        $this->assertSame($tahap, $res['tahapSaya']);
        $this->assertCount(1, $res['data']);
        $this->assertSame($milikSaya->id, $res['data'][0]['id']);
    }

    public function test_role_birokrasi_tetap_melihat_yang_pernah_diprosesnya(): void
    {
        // Sudah lewat tahap koordinator — bukan gilirannya lagi, tapi dia yang
        // dulu menyetujuinya, jadi tetap terlihat sebagai riwayat.
        $lama = $this->pinjaman('pending_coo', ['approvals' => [
            ['role' => 'auditor', 'user' => 'aziz', 'action' => 'submit', 'at' => now()->toDateTimeString()],
            ['role' => 'koordinator', 'user' => 'koor1', 'action' => 'approve', 'at' => now()->toDateTimeString()],
        ]]);

        // Diproses koordinator LAIN — tidak boleh ikut terlihat.
        $this->pinjaman('pending_coo', ['approvals' => [
            ['role' => 'koordinator', 'user' => 'koor2', 'action' => 'approve', 'at' => now()->toDateTimeString()],
        ]]);

        $res = $this->daftar('koordinator', ['username' => 'koor1']);

        $this->assertCount(1, $res['data']);
        $this->assertSame($lama->id, $res['data'][0]['id']);
    }

    public function test_role_di_luar_daftar_tidak_menerima_apa_apa(): void
    {
        $this->pinjaman('pending_koordinator');

        $res = $this->daftar('h1');

        $this->assertSame([], $res['data']);
        $this->assertFalse($res['bolehLihatSemua']);
    }

    // ── Penanda "bisa diproses" ──────────────────────────────────────────────

    public function test_hanya_pemegang_tahap_yang_ditandai_bisa_memproses(): void
    {
        $this->pinjaman('pending_bpk');

        $this->assertTrue($this->daftar('bpk')['data'][0]['bisaDiproses']);
        $this->assertTrue($this->daftar('admin')['data'][0]['bisaDiproses'], 'Admin tetap bisa mengoreksi.');
        $this->assertFalse($this->daftar('coo')['data'][0]['bisaDiproses'], 'Tahap COO sudah lewat.');
    }

    public function test_pengajuan_selesai_tidak_bisa_diproses_siapa_pun(): void
    {
        $this->pinjaman('approved');

        $this->assertFalse($this->daftar('admin')['data'][0]['bisaDiproses']);
        $this->assertFalse($this->daftar('manajer')['data'][0]['bisaDiproses']);
    }

    // ── Penegakan giliran di endpoint approve ────────────────────────────────

    public function test_role_yang_bukan_gilirannya_ditolak_server(): void
    {
        $p = $this->pinjaman('pending_bpk');

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/pinjaman-cabang/{$p->id}/approve", ['action' => 'approve'])
            ->assertForbidden();

        // Yang penting: statusnya TIDAK melompat ke approved.
        $this->assertSame('pending_bpk', $p->fresh()->status);
    }

    public function test_pemegang_tahap_tetap_bisa_memproses(): void
    {
        $p = $this->pinjaman('pending_bpk');

        Sanctum::actingAs(User::factory()->create(['role' => 'bpk']));
        $this->postJson("/api/pinjaman-cabang/{$p->id}/approve", ['action' => 'approve'])->assertOk();

        $this->assertSame('approved', $p->fresh()->status);
    }

    public function test_admin_boleh_memproses_tahap_mana_pun(): void
    {
        $p = $this->pinjaman('pending_unit');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/pinjaman-cabang/{$p->id}/approve", ['action' => 'approve'])->assertOk();

        $this->assertSame('pending_bpk', $p->fresh()->status);
    }

    public function test_pengajuan_yang_sudah_selesai_tidak_bisa_diproses_lagi(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            $p = $this->pinjaman($status);

            Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
            $this->postJson("/api/pinjaman-cabang/{$p->id}/approve", ['action' => 'approve'])
                ->assertStatus(422);

            $this->assertSame($status, $p->fresh()->status);
        }
    }

    // ── Penyaringan & data pendamping ────────────────────────────────────────

    public function test_filter_jenis_dan_status_dikerjakan_server(): void
    {
        $this->pinjaman('pending_koordinator', ['jenis' => 'BPK']);
        $this->pinjaman('approved', ['jenis' => 'BPB']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertCount(1, $this->getJson('/api/pinjaman-cabang/daftar?jenis=BPB')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/pinjaman-cabang/daftar?status=approved')->assertOk()->json('data'));
        $this->assertCount(0, $this->getJson('/api/pinjaman-cabang/daftar?jenis=BPK&status=approved')->assertOk()->json('data'));
    }

    public function test_tiap_baris_membawa_plan_asalnya(): void
    {
        $this->pinjaman('pending_koordinator');

        $baris = $this->daftar('admin')['data'][0];

        $this->assertSame('0501/12/09/2026/SPT-IAT', $baris['plan']['noSpt']);
        $this->assertSame('CSC LANGSA', $baris['plan']['cabang']);
    }

    public function test_daftar_membawa_alur_tiap_jenis_untuk_penunjuk_kemajuan(): void
    {
        $this->pinjaman('pending_koordinator');

        $res = $this->daftar('admin');

        // Penunjuk kemajuan di layar ("tahap 3 dari 5") memakai urutan ini.
        // Dikirim server supaya tidak ada salinan terpisah di browser yang
        // diam-diam melenceng ketika alurnya berubah.
        $this->assertSame(PinjamanCabang::FLOW_BPK, $res['alur']['BPK']);
        $this->assertSame(PinjamanCabang::FLOW_BPB, $res['alur']['BPB']);
    }

    public function test_rute_daftar_tidak_tertangkap_sebagai_id(): void
    {
        // "daftar" akan cocok dengan /pinjaman-cabang/{id} kalau rutenya salah
        // urutan atau {id} tidak dibatasi angka.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/pinjaman-cabang/daftar')
            ->assertOk()
            ->assertJsonStructure(['data', 'bolehLihatSemua', 'tahapSaya', 'alur']);
    }
}
