<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditTask;
use App\Models\PinjamanCabang;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\PlanTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dokumen yang DITOLAK harus kembali ke meja pengajunya — bisa diperbaiki, dan
 * pengajunya diberi tahu.
 *
 * Sebelum perubahan ini keduanya buntu:
 *
 * - Plan audit yang ditolak memang kembali ke status draft, tapi rute
 *   PUT /api/plans/{plan} dan tombol Edit-nya dikunci untuk admin saja. Auditor
 *   yang plannya ditolak cuma bisa menekan "Ajukan" lagi — mengirim ulang
 *   dokumen yang sama persis yang baru saja ditolak. Notifikasi yang diterimanya
 *   pun cuma berbunyi "masih berstatus Draft — tekan Ajukan", tanpa menyebut
 *   bahwa plannya ditolak apalagi alasannya.
 *
 * - Pinjaman BPK/BPB lebih buntu lagi: 'rejected' bukan bagian dari FLOW_BPK
 *   maupun FLOW_BPB sehingga nextStatus() mengembalikan null, tidak ada endpoint
 *   edit sama sekali, dan alur pinjaman tidak pernah mengirim satu pun
 *   notifikasi. Auditor baru tahu pengajuannya ditolak kalau kebetulan membuka
 *   lagi task-nya — dan kalau task itu sudah ditandai selesai, task-nya sendiri
 *   sudah hilang dari daftarnya.
 */
class PenolakanBisaDiperbaikiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $status = 'draft', array $ganti = []): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt'      => '0501/12/09/2026/SPT-IAT',
            'cabang'      => 'CSC LANGSA',
            'cabang_area' => 'ACEH',
            'jenis_audit' => 'Audit Full CSC',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => ['Salim'],
            'status'      => $status,
            'created_by'  => 'aziz',
            ...$ganti,
        ]);
    }

    private function isiPlan(PlanAudit $plan, array $ganti = []): array
    {
        return [
            'no_spt'      => $plan->no_spt,
            'jenis_audit' => $plan->jenis_audit,
            'cabang'      => $plan->cabang,
            'kepala_tim'  => $plan->kepala_tim,
            'tim'         => $plan->tim ?: [],
            'keterangan'  => 'Sudah diperbaiki sesuai catatan koordinator.',
            ...$ganti,
        ];
    }

    // ── Plan audit ───────────────────────────────────────────────────────────

    public function test_auditor_pemilik_bisa_mengedit_plan_yang_ditolak(): void
    {
        $plan = $this->plan('draft');
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $this->putJson("/api/plans/{$plan->id}", $this->isiPlan($plan, ['cabang' => 'CSC MEULABOH']))
            ->assertOk()
            ->assertJsonPath('data.cabang', 'CSC MEULABOH');

        $this->assertSame('Sudah diperbaiki sesuai catatan koordinator.', $plan->fresh()->keterangan);
    }

    public function test_anggota_tim_juga_bisa_mengedit_bukan_hanya_kepala_tim(): void
    {
        $plan = $this->plan('draft');
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'display_name' => 'Salim']));

        $this->putJson("/api/plans/{$plan->id}", $this->isiPlan($plan))->assertOk();
    }

    public function test_auditor_di_luar_tim_tidak_bisa_mengedit_plan_orang_lain(): void
    {
        $plan = $this->plan('draft');
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Orang Lain']));

        $this->putJson("/api/plans/{$plan->id}", $this->isiPlan($plan))->assertForbidden();
        $this->assertNull($plan->fresh()->keterangan);
    }

    public function test_plan_yang_sudah_diajukan_membeku_lagi_untuk_auditor(): void
    {
        $plan = $this->plan('pending_koordinator');
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']));

        $this->putJson("/api/plans/{$plan->id}", $this->isiPlan($plan))
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_admin_tetap_bisa_mengedit_plan_pada_status_apa_pun(): void
    {
        $plan = $this->plan('pending_manajer');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'name' => 'Administrator']));

        $this->putJson("/api/plans/{$plan->id}", $this->isiPlan($plan))->assertOk();
    }

    public function test_notifikasi_penolakan_menyebut_alasan_dan_menyuruh_memperbaiki(): void
    {
        $plan = $this->plan('pending_koordinator');
        $auditor = User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']);

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/plans/{$plan->id}/reject", ['alasan' => 'Tanggal pelaksanaan bentrok'])->assertOk();

        $notif = AppNotification::where('user_id', $auditor->id)->where('step_key', 'reject')->first();

        $this->assertNotNull($notif, 'Penolakan plan tidak memberi tahu auditornya.');
        $this->assertStringContainsString('ditolak', mb_strtolower($notif->title));
        $this->assertStringContainsString('Tanggal pelaksanaan bentrok', $notif->message);
        $this->assertStringContainsString('perbaiki', mb_strtolower($notif->message));
        $this->assertSame('/akta/plan-audit?id=' . $plan->id, $notif->url);
    }

    public function test_penolakan_hanya_menyapa_tim_plan_bukan_seluruh_auditor(): void
    {
        $plan = $this->plan('pending_koordinator');
        $tim = User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']);
        $lain = User::factory()->create(['role' => 'auditor', 'name' => 'Auditor Cabang Lain']);

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/plans/{$plan->id}/reject", ['alasan' => 'Salah cabang'])->assertOk();

        $this->assertSame(1, AppNotification::where('user_id', $tim->id)->where('step_key', 'reject')->count());
        $this->assertSame(0, AppNotification::where('user_id', $lain->id)->where('step_key', 'reject')->count());
    }

    public function test_penolakan_kedua_tetap_sampai_walau_masih_dalam_jeda_pengingat(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']);
        $koordinator = User::factory()->create(['role' => 'koordinator']);

        $plan = $this->plan('pending_koordinator');

        // Jeda pengingat 20 jam sengaja TIDAK berlaku untuk penolakan: penolakan
        // kedua atas plan yang sama adalah kabar baru yang wajib sampai.
        foreach (['Tanggal salah', 'Tim belum lengkap'] as $alasan) {
            $plan->refresh()->update(['status' => 'pending_koordinator']);

            Sanctum::actingAs($koordinator);
            $this->postJson("/api/plans/{$plan->id}/reject", ['alasan' => $alasan])->assertOk();
        }

        $this->assertSame(2, AppNotification::where('user_id', $auditor->id)->where('step_key', 'reject')->count());
    }

    public function test_mengajukan_ulang_menutup_notifikasi_penolakan(): void
    {
        $plan = $this->plan('pending_koordinator');
        $auditor = User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz']);

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/plans/{$plan->id}/reject", ['alasan' => 'Perbaiki dulu'])->assertOk();

        Sanctum::actingAs($auditor);
        $this->postJson("/api/plans/{$plan->id}/advance")->assertOk();

        $notif = AppNotification::where('user_id', $auditor->id)->where('step_key', 'reject')->first();
        $this->assertNotNull($notif->read_at, 'Kabar penolakan masih menempel walau plannya sudah diajukan ulang.');
    }

    // ── Pinjaman BPK / BPB ───────────────────────────────────────────────────

    private function siapkanPinjaman(string $jenis = 'BPK', string $status = 'rejected'): array
    {
        $auditor = User::factory()->create(['role' => 'auditor', 'name' => 'Abdul Aziz', 'username' => 'aziz']);

        $plan = $this->plan('running', ['tim' => []]);
        app(PlanTaskService::class)->syncPlan($plan);
        $task = AuditTask::where('plan_audit_id', $plan->id)->firstOrFail();

        $pinjaman = PinjamanCabang::query()->create([
            'audit_task_id'    => $task->id,
            'jenis'            => $jenis,
            'cabang_realisasi' => ['SO ARK'],
            'no_spd'           => '6705/CDN/TR/09/26',
            'nominal'          => 5000000,
            'terbilang'        => 'Lima Juta Rupiah',
            'departemen'       => 'Finance',
            'status'           => $status,
            'approvals'        => [['role' => 'auditor', 'user' => 'aziz', 'action' => 'submit', 'at' => now()->toDateTimeString()]],
            'created_by'       => 'aziz',
        ]);

        return [$auditor, $task, $pinjaman];
    }

    public function test_pinjaman_ditolak_bisa_diperbaiki_lalu_diajukan_ulang_dari_awal(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPK');
        Sanctum::actingAs($auditor);

        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", [
            'no_spd'  => '6706/CDN/TR/09/26',
            'nominal' => 4500000,
            'catatan' => 'Nominal dikoreksi sesuai catatan koordinator',
        ])->assertOk();

        $segar = $pinjaman->fresh();
        $this->assertSame('pending_koordinator', $segar->status);
        $this->assertSame('6706/CDN/TR/09/26', $segar->no_spd);
        $this->assertSame(4500000.0, $segar->nominal);
        $jejak = $segar->approvals;
        $this->assertSame('resubmit', end($jejak)['action']);
    }

    public function test_bpb_diajukan_ulang_ke_tahap_pertama_alurnya_sendiri(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPB');
        Sanctum::actingAs($auditor);

        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", ['nominal' => 1000000])->assertOk();

        $this->assertSame(PinjamanCabang::FLOW_BPB[0], $pinjaman->fresh()->status);
    }

    public function test_bukti_lama_dipertahankan_saat_tidak_ada_file_baru(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPK');
        $pinjaman->update(['bukti_file' => 'pinjaman/bukti/lama.pdf']);
        Sanctum::actingAs($auditor);

        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", ['nominal' => 4000000])->assertOk();

        $this->assertSame('pinjaman/bukti/lama.pdf', $pinjaman->fresh()->bukti_file);
    }

    public function test_pinjaman_yang_belum_ditolak_tidak_bisa_diedit(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPK', 'pending_manajer');
        Sanctum::actingAs($auditor);

        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", ['nominal' => 1])
            ->assertStatus(422);

        $this->assertSame('pending_manajer', $pinjaman->fresh()->status);
    }

    public function test_auditor_lain_tidak_bisa_memperbaiki_pengajuan_orang(): void
    {
        [, , $pinjaman] = $this->siapkanPinjaman('BPK');
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'username' => 'orang-lain']));

        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", ['nominal' => 1])->assertForbidden();
        $this->assertSame('rejected', $pinjaman->fresh()->status);
    }

    public function test_penolakan_pinjaman_memberi_tahu_pengajunya(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPK', 'pending_koordinator');

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/pinjaman-cabang/{$pinjaman->id}/approve", [
            'action' => 'reject',
            'note'   => 'Nominal melebihi plafon',
        ])->assertOk();

        $notif = AppNotification::where('user_id', $auditor->id)->where('step_key', 'reject')->first();

        $this->assertNotNull($notif, 'Penolakan pinjaman tidak memberi tahu siapa pun.');
        $this->assertStringContainsString('BPK', $notif->title);
        $this->assertStringContainsString('Nominal melebihi plafon', $notif->message);
        $this->assertSame('/akta/task?pinjaman=' . $pinjaman->id, $notif->url);
    }

    public function test_penolakan_membuka_kembali_task_yang_sudah_ditandai_selesai(): void
    {
        [, $task, $pinjaman] = $this->siapkanPinjaman('BPK', 'pending_koordinator');
        $task->update(['status' => 'done']);

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/pinjaman-cabang/{$pinjaman->id}/approve", ['action' => 'reject', 'note' => 'Salah SPD'])
            ->assertOk();

        // Task selesai disembunyikan dari daftar auditor (lihat
        // AuditTaskController::index) — kalau dibiarkan selesai, pengajuan yang
        // ditolak tidak punya pintu untuk dibuka lagi.
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_menyetujui_tidak_ikut_membuka_kembali_task_selesai(): void
    {
        [, $task, $pinjaman] = $this->siapkanPinjaman('BPK', 'pending_koordinator');
        $task->update(['status' => 'done']);

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/pinjaman-cabang/{$pinjaman->id}/approve", ['action' => 'approve'])->assertOk();

        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_mengajukan_ulang_pinjaman_menutup_notifikasi_penolakannya(): void
    {
        [$auditor, , $pinjaman] = $this->siapkanPinjaman('BPK', 'pending_koordinator');

        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->postJson("/api/pinjaman-cabang/{$pinjaman->id}/approve", ['action' => 'reject', 'note' => 'Ulangi'])->assertOk();

        Sanctum::actingAs($auditor);
        $this->putJson("/api/pinjaman-cabang/{$pinjaman->id}", ['nominal' => 3000000])->assertOk();

        $notif = AppNotification::where('user_id', $auditor->id)->where('step_key', 'reject')->first();
        $this->assertNotNull($notif->read_at);
    }
}
