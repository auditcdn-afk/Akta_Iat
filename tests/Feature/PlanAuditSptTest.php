<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\PlanAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cetak "Surat Perintah Tugas" (SPT) per plan audit — halaman bebas bentuk,
 * tapi dua hal wajib: (1) kalimat tugasnya menyesuaikan jenis_audit, bukan
 * generik untuk semua, dan (2) waktu tiap tahap birokrasi (diajukan/disetujui/
 * mulai/selesai) diambil real-time dari plan_audit_logs, bukan field statis —
 * tahap yang belum terjadi harus tetap tampil sebagai "belum", bukan hilang
 * atau ikut terisi tanggal sekarang.
 */
class PlanAuditSptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function buatPlan(array $override = []): PlanAudit
    {
        return PlanAudit::query()->create(array_merge([
            'no_spt' => '0001/TEST/SPT-IAT',
            'cabang' => 'CSC TEST',
            'cabang_area' => 'AREA TEST',
            'jenis_audit' => 'Audit Full SO',
            'kepala_tim' => 'Abdul Aziz',
            'tim' => ['Abdul Aziz', 'SARI\'T'],
            'status' => 'draft',
        ], $override));
    }

    public function test_halaman_spt_bisa_dibuka(): void
    {
        $plan = $this->buatPlan();

        $this->get(route('akta.plan-audit.spt', $plan))
            ->assertOk()
            ->assertSee('SURAT PERINTAH TUGAS')
            ->assertSee('0001/TEST/SPT-IAT')
            ->assertSee('Abdul Aziz');
    }

    public function test_kalimat_tugas_menyesuaikan_jenis_audit(): void
    {
        $so = $this->buatPlan(['jenis_audit' => 'Audit Full SO']);
        $htmlSo = $this->get(route('akta.plan-audit.spt', $so))->assertOk()->getContent();
        $this->assertStringContainsString('area operasional Sales Office', $htmlSo);

        $kasir = $this->buatPlan(['jenis_audit' => 'Audit Serah Terima Kasir', 'no_spt' => '0002/TEST/SPT-IAT']);
        $htmlKasir = $this->get(route('akta.plan-audit.spt', $kasir))->assertOk()->getContent();
        $this->assertStringContainsString('serah terima jabatan Kasir', $htmlKasir);
        $this->assertStringNotContainsString('area operasional Sales Office', $htmlKasir);
    }

    public function test_jenis_audit_tidak_dikenal_tetap_tampil_bukan_error(): void
    {
        $plan = $this->buatPlan(['jenis_audit' => 'Audit Khusus Belum Terdaftar']);

        $this->get(route('akta.plan-audit.spt', $plan))
            ->assertOk()
            ->assertSee('Audit Khusus Belum Terdaftar', false);
    }

    public function test_tahap_yang_belum_terjadi_tampil_belum_bukan_tanggal_hari_ini(): void
    {
        $plan = $this->buatPlan();

        $html = $this->get(route('akta.plan-audit.spt', $plan))->assertOk()->getContent();

        // Belum ada log sama sekali -> semua tahap "Belum terjadi", tidak ada
        // satu pun baris yang terisi (bukan cuma aturan CSS-nya di <style>,
        // makanya cek tag <td> yang benar-benar dipakai, bukan nama class-nya saja).
        $this->assertSame(7, substr_count($html, 'Belum terjadi'));
        $this->assertStringNotContainsString('<td class="sudah-waktu">', $html);
    }

    public function test_waktu_tahap_yang_sudah_terjadi_diambil_dari_log_real(): void
    {
        $plan = $this->buatPlan(['status' => 'running']);

        // created_at/updated_at BUKAN mass-assignable di PlanAuditLog (lihat
        // $fillable model-nya) — kalau dikirim lewat create() akan diam-diam
        // diabaikan dan Eloquent isi otomatis dengan "sekarang". Waktu palsu
        // di masa lalu di sini harus dipaksa lewat forceFill()->save().
        $isiWaktu = function (array $attrs, $waktu) {
            $log = new PlanAuditLog($attrs);
            $log->forceFill(['created_at' => $waktu, 'updated_at' => $waktu])->save();
            return $log;
        };

        $isiWaktu([
            'plan_audit_id' => $plan->id, 'action' => 'created',
            'from_status' => null, 'to_status' => 'draft', 'actor' => 'admin',
        ], now()->subDays(5));
        $waktuKoordinator = now()->subDays(3);
        $isiWaktu([
            'plan_audit_id' => $plan->id, 'action' => 'advance',
            'from_status' => 'pending_koordinator', 'to_status' => 'pending_manajer',
            'actor' => 'koordinator1',
        ], $waktuKoordinator);
        $waktuMulai = now()->subDay();
        $isiWaktu([
            'plan_audit_id' => $plan->id, 'action' => 'advance',
            'from_status' => 'scheduled', 'to_status' => 'running',
            'actor' => 'abdulaziz',
        ], $waktuMulai);

        User::factory()->create(['username' => 'koordinator1', 'display_name' => 'Budi Koordinator', 'role' => 'koordinator']);
        User::factory()->create(['username' => 'abdulaziz', 'display_name' => 'Abdul Aziz', 'role' => 'auditor']);

        $html = $this->get(route('akta.plan-audit.spt', $plan))->assertOk()->getContent();

        $this->assertStringContainsString($waktuKoordinator->format('d/m/Y H:i'), $html);
        $this->assertStringContainsString('Budi Koordinator', $html);
        $this->assertStringContainsString($waktuMulai->format('d/m/Y H:i'), $html);

        // 3 dari 7 tahap terisi (Diajukan, Disetujui Koordinator, Mulai Audit) ->
        // sisanya (Disetujui Manajer, Disetujui COO, Tiba di Unit Usaha, Selesai) belum.
        $this->assertSame(4, substr_count($html, 'Belum terjadi'));
    }

    public function test_jabatan_kepala_tim_diambil_dari_role_user_yang_cocok(): void
    {
        User::factory()->create(['display_name' => 'Abdul Aziz', 'role' => 'auditor']);
        $plan = $this->buatPlan(['kepala_tim' => 'Abdul Aziz']);

        $this->get(route('akta.plan-audit.spt', $plan))
            ->assertOk()
            ->assertSee('Internal Auditor');
    }
}
