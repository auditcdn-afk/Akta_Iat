<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PinjamanCabang;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cetak "Memo Pinjaman" untuk satu pengajuan pinjaman cabang (BPK/BPB) —
 * pola sama dengan SPT Plan Audit: tahapan approval real-time diambil dari
 * kolom `approvals` yang sudah tersimpan (bukan tanda tangan kosong), dan
 * jumlah/urutan tahapnya menyesuaikan jenis (BPK 5 tahap, BPB 3 tahap).
 */
class PinjamanMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function buatTask(): AuditTask
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0407/28/07/2026/SPT-IAT', 'cabang' => 'CSC AGL',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
            'kepala_tim' => 'Heri Syahputra', 'tim' => ['Heri Syahputra'],
        ]);

        return AuditTask::create([
            'plan_audit_id' => $plan->id, 'judul' => 'Audit Full CSC - CSC AGL',
            'assigned_to' => 'CSC AGL', 'status' => 'todo', 'created_by' => 'admin',
        ]);
    }

    public function test_halaman_memo_bpk_bisa_dibuka_dengan_5_tahap(): void
    {
        $task = $this->buatTask();
        $pinjaman = PinjamanCabang::create([
            'audit_task_id' => $task->id, 'jenis' => 'BPK',
            'cabang_realisasi' => ['POS AGL'], 'no_spd' => '5532/CDN/TR/07/26',
            'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'status' => 'pending_koordinator',
            'approvals' => [['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => now()->toDateTimeString()]],
            'created_by' => 'heris',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $pinjaman))->assertOk()->getContent();

        $this->assertStringContainsString('MEMO PINJAMAN', $html);
        $this->assertStringContainsString('POS AGL', $html);
        $this->assertStringContainsString('Rp 5.000.000', $html);
        $this->assertStringContainsString('Lima Juta Rupiah', $html);
        $this->assertStringContainsString('5532/CDN/TR/07/26', $html);
        $this->assertStringContainsString('Disetujui Koordinator', $html);
        $this->assertStringContainsString('Disetujui Manajer Audit', $html);
        $this->assertStringContainsString('Disetujui COO', $html);
        $this->assertStringContainsString('Disetujui Unit Usaha', $html);
        $this->assertStringContainsString('Disetujui Role BPK', $html);
    }

    public function test_halaman_memo_bpb_hanya_3_tahap_tanpa_coo_dan_unit(): void
    {
        $task = $this->buatTask();
        $pinjaman = PinjamanCabang::create([
            'audit_task_id' => $task->id, 'jenis' => 'BPB',
            'departemen' => 'Finance', 'nominal' => 2000000, 'terbilang' => 'Dua Juta Rupiah',
            'catatan' => 'Keperluan operasional kantor pusat',
            'status' => 'pending_koordinator',
            'approvals' => [['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => now()->toDateTimeString()]],
            'created_by' => 'heris',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $pinjaman))->assertOk()->getContent();

        $this->assertStringContainsString('Bon Pinjaman ke Finance (BPB)', $html);
        $this->assertStringContainsString('Disetujui Koordinator', $html);
        $this->assertStringContainsString('Disetujui Manajer Audit', $html);
        $this->assertStringContainsString('Disetujui Role BPK', $html);
        $this->assertStringNotContainsString('Disetujui COO', $html);
        $this->assertStringNotContainsString('Disetujui Unit Usaha', $html);
    }

    public function test_waktu_dan_aktor_tahap_yang_sudah_approve_diambil_dari_data_nyata(): void
    {
        User::factory()->create(['username' => 'koordinator1', 'display_name' => 'Budi Koordinator', 'role' => 'koordinator']);

        $task = $this->buatTask();
        $waktuKoordinator = now()->subDays(2);
        $pinjaman = PinjamanCabang::create([
            'audit_task_id' => $task->id, 'jenis' => 'BPB',
            'departemen' => 'Finance', 'nominal' => 1000000, 'terbilang' => 'Satu Juta Rupiah',
            'status' => 'pending_manajer',
            'approvals' => [
                ['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => now()->subDays(3)->toDateTimeString()],
                ['role' => 'koordinator', 'user' => 'koordinator1', 'action' => 'approve', 'note' => '', 'at' => $waktuKoordinator->toDateTimeString()],
            ],
            'created_by' => 'heris',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $pinjaman))->assertOk()->getContent();

        $this->assertStringContainsString($waktuKoordinator->format('d/m/Y H:i'), $html);
        $this->assertStringContainsString('Budi Koordinator', $html);
        // BPB punya 3 tahap (Koordinator/Manajer/BPK); baru Koordinator yang
        // terjadi, jadi Manajer & Role BPK masih "Belum terjadi".
        $this->assertSame(2, substr_count($html, 'Belum terjadi'));
    }

    public function test_tahap_setelah_penolakan_tetap_belum_terjadi(): void
    {
        $task = $this->buatTask();
        $pinjaman = PinjamanCabang::create([
            'audit_task_id' => $task->id, 'jenis' => 'BPB',
            'departemen' => 'Finance', 'nominal' => 500000, 'terbilang' => 'Lima Ratus Ribu Rupiah',
            'status' => 'rejected',
            'approvals' => [
                ['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => now()->subDays(2)->toDateTimeString()],
                ['role' => 'koordinator', 'user' => 'koordinator1', 'action' => 'reject', 'note' => 'Tidak sesuai', 'at' => now()->subDay()->toDateTimeString()],
            ],
            'created_by' => 'heris',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $pinjaman))->assertOk()->getContent();

        $this->assertStringContainsString('DITOLAK', $html);
        $this->assertStringContainsString('Ditolak', $html); // status chip
        // Manajer & BPK belum sempat terjadi karena sudah ditolak di Koordinator.
        $this->assertSame(2, substr_count($html, 'Belum terjadi'));
    }

    public function test_nama_pengaju_diambil_dari_display_name_bukan_username(): void
    {
        User::factory()->create(['username' => 'heris', 'display_name' => 'Heri Syahputra']);
        $task = $this->buatTask();
        $pinjaman = PinjamanCabang::create([
            'audit_task_id' => $task->id, 'jenis' => 'BPK',
            'cabang_realisasi' => ['POS AGL'], 'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'status' => 'pending_koordinator',
            'approvals' => [['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => now()->toDateTimeString()]],
            'created_by' => 'heris',
        ]);

        $this->get(route('akta.pinjaman.memo', $pinjaman))
            ->assertOk()
            ->assertSee('Heri Syahputra');
    }
}
