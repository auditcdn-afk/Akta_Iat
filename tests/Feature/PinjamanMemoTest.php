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

    /** Pengajuan lengkap sampai tahap tertentu, untuk menguji tanggal terbit. */
    private function pinjamanDenganApproval(string $jenis, array $tahap, array $ganti = []): PinjamanCabang
    {
        $approvals = [['role' => 'auditor', 'user' => 'heris', 'action' => 'submit', 'at' => '2026-09-10 02:07:00']];
        foreach ($tahap as $role => $at) {
            $approvals[] = ['role' => $role, 'user' => $role . '1', 'action' => 'approve', 'at' => $at];
        }

        return PinjamanCabang::create([
            'audit_task_id' => $this->buatTask()->id,
            'jenis' => $jenis,
            'cabang_realisasi' => $jenis === 'BPK' ? ['SO ARK'] : [],
            'no_spd' => '6705/CDN/TR/09/26',
            'nominal' => 5000000, 'terbilang' => 'Lima Juta Rupiah',
            'departemen' => 'Finance',
            'status' => 'pending_bpk',
            'approvals' => $approvals,
            'created_by' => 'heris',
            ...$ganti,
        ]);
    }

    // ── Tanggal terbit mengikuti persetujuan penerbitnya ─────────────────────

    public function test_bpb_terbit_pada_tanggal_persetujuan_manajer(): void
    {
        User::factory()->create(['username' => 'manajer1', 'display_name' => 'Yosep', 'role' => 'manajer']);

        $p = $this->pinjamanDenganApproval('BPB', [
            'koordinator' => '2026-09-10 02:38:00',
            'manajer'     => '2026-09-12 09:15:00',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Diterbitkan, 12/09/2026', $html);
        $this->assertStringContainsString('Yosep', $html);
        $this->assertStringContainsString('Manajer Audit', $html);
        // Tanggal cetak TIDAK boleh dipakai — memo yang dicetak ulang bulan
        // depan harus tetap menunjukkan tanggal terbit yang sama.
        $this->assertStringNotContainsString('Diterbitkan, ' . now()->format('d/m/Y'), $html);
    }

    public function test_bpk_terbit_pada_tanggal_persetujuan_coo(): void
    {
        User::factory()->create(['username' => 'coo1', 'display_name' => 'Chief Operating Officer', 'role' => 'coo']);

        $p = $this->pinjamanDenganApproval('BPK', [
            'koordinator' => '2026-09-11 04:11:00',
            'manajer'     => '2026-09-11 07:41:00',
            'coo'         => '2026-09-11 08:48:00',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Diterbitkan, 11/09/2026', $html);
        $this->assertStringContainsString('Chief Operating Officer', $html);
    }

    public function test_memo_belum_terbit_selama_penerbitnya_belum_menyetujui(): void
    {
        // BPB baru sampai koordinator — manajer belum menyetujui.
        $p = $this->pinjamanDenganApproval('BPB', ['koordinator' => '2026-09-10 02:38:00'],
            ['status' => 'pending_manajer']);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Belum diterbitkan', $html);
        $this->assertStringNotContainsString('Diterbitkan, ', $html);
    }

    public function test_bpb_tidak_ditandatangani_coo(): void
    {
        // COO bahkan tidak ada dalam FLOW_BPB — memo BPB tidak boleh
        // menampilkannya sebagai penerbit.
        $p = $this->pinjamanDenganApproval('BPB', [
            'koordinator' => '2026-09-10 02:38:00',
            'manajer'     => '2026-09-12 09:15:00',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringNotContainsString('Chief Operating Officer', $html);
    }

    // ── Cabang Peminjaman & urutan blok ─────────────────────────────────────

    public function test_bpk_menampilkan_cabang_peminjaman_dari_unit_yang_dipilih(): void
    {
        $p = $this->pinjamanDenganApproval('BPK', [], ['cabang_realisasi' => ['SO UJT']]);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringContainsString('Cabang Peminjaman', $html);
        $this->assertStringContainsString('SO UJT', $html);
    }

    public function test_bpb_tidak_menampilkan_cabang_peminjaman(): void
    {
        // BPB tidak punya cabang realisasi — barisnya tidak boleh muncul kosong.
        $p = $this->pinjamanDenganApproval('BPB', []);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $this->assertStringNotContainsString('Cabang Peminjaman', $html);
    }

    public function test_tahapan_approval_berada_di_bawah_blok_penerbitan(): void
    {
        $p = $this->pinjamanDenganApproval('BPK', [
            'koordinator' => '2026-09-11 04:11:00',
            'manajer'     => '2026-09-11 07:41:00',
            'coo'         => '2026-09-11 08:48:00',
        ]);

        $html = $this->get(route('akta.pinjaman.memo', $p))->assertOk()->getContent();

        $posisiTerbit  = strpos($html, 'Diterbitkan,');
        $posisiTahapan = strpos($html, 'Tahapan Approval');

        $this->assertNotFalse($posisiTerbit);
        $this->assertNotFalse($posisiTahapan);
        $this->assertLessThan($posisiTahapan, $posisiTerbit,
            'Blok "Diterbitkan" harus berada di atas tabel Tahapan Approval.');
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
