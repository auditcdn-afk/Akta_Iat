<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mengunci perubahan yang menghilangkan payload dan round-trip berlebih —
 * lihat audit performa pada branch ini.
 */
class PerformancePayloadTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create([
            'username'   => 'admin',
            'role'       => 'admin',
            'unit_usaha' => 'HO',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function makePlan(): PlanAudit
    {
        $plan = PlanAudit::query()->create([
            'no_spt'      => '0001/01/01/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'scheduled',
        ]);

        $plan->recordLog('created', null, 'scheduled', null, 'Plan dibuat');

        return $plan;
    }

    public function test_daftar_plan_tidak_lagi_mengirim_riwayat_status(): void
    {
        $this->actingAsAdmin();
        $this->makePlan();

        $response = $this->getJson('/api/plans');

        $response->assertOk();
        // Riwayat hanya dipakai modal detail; ikut di setiap baris daftar akan
        // membuat response tumbuh terus seiring bertambahnya log.
        $this->assertSame([], $response->json('data.0.logs'));
    }

    public function test_daftar_plan_masih_bisa_menyertakan_riwayat_bila_diminta(): void
    {
        $this->actingAsAdmin();
        $this->makePlan();

        $response = $this->getJson('/api/plans?with_logs=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.0.logs'));
    }

    public function test_detail_plan_selalu_menyertakan_riwayat_status(): void
    {
        $this->actingAsAdmin();
        $plan = $this->makePlan();

        $response = $this->getJson("/api/plans/{$plan->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.logs'));
        $this->assertSame('created', $response->json('data.logs.0.action'));
    }

    /**
     * Sama seperti /api/plans, daftar task juga sempat mengirim riwayat status
     * plan untuk SETIAP baris — padahal riwayat itu hanya dipakai modal detail
     * (renderTimeline di akta-task.js). Karena relasinya tidak di-eager-load
     * secara benar, tiap task juga memicu satu query sendiri: pada 600 task itu
     * 603 query dan >1,6 MB JSON, turun jadi 3 query dan ~370 KB.
     */
    public function test_daftar_task_tidak_lagi_mengirim_riwayat_status_dan_bebas_query_per_baris(): void
    {
        $this->actingAsAdmin();

        for ($i = 1; $i <= 5; $i++) {
            $plan = PlanAudit::query()->create([
                'no_spt'      => sprintf('%04d/01/01/2026/SPT-IAT', $i),
                'cabang'      => 'CSC TBH',
                'jenis_audit' => 'Audit',
                'status'      => 'cabang_active',
            ]);
            $plan->recordLog('created', null, 'cabang_active', null, 'Plan dibuat');
            $plan->tasks()->create([
                'judul'       => 'Pelaksanaan audit ' . $i,
                'kategori'    => 'Audit',
                'assigned_to' => 'admin',
                'status'      => 'todo',
            ]);
        }

        DB::enableQueryLog();
        $response = $this->getJson('/api/tasks');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame([], $response->json('data.0.planAudit.logs'));

        // Jumlah query harus tetap sama berapa pun banyaknya task. Ambang 12
        // memberi ruang untuk query auth/cache/sinkronisasi task, tapi jauh di
        // bawah pola satu-query-per-baris (yang untuk 5 task saja > 15).
        $this->assertLessThan(12, $queries, "Terindikasi query per baris: {$queries} query untuk 5 task.");
    }

    /** Riwayat status tetap bisa diminta secara eksplisit untuk modal detail. */
    public function test_daftar_task_masih_bisa_menyertakan_riwayat_bila_diminta(): void
    {
        $this->actingAsAdmin();

        $plan = $this->makePlan();
        $plan->tasks()->create([
            'judul'       => 'Pelaksanaan audit',
            'kategori'    => 'Audit',
            'assigned_to' => 'admin',
            'status'      => 'todo',
        ]);

        $response = $this->getJson('/api/tasks?with_logs=1');

        $response->assertOk();
        $this->assertSame('created', $response->json('data.0.planAudit.logs.0.action'));
    }

    /**
     * /api/all-data mengirim SELURUH isi data store ke browser, dan
     * /api/all-data/summary hanya menghitung key-nya. Keduanya cuma dipakai
     * kartu status di halaman dashboard lama yang sudah tidak punya rute —
     * artinya setiap pemuatan halaman menanggung satu request yang hasilnya
     * tidak pernah ditampilkan. Keduanya dihapus; tes ini menjaga agar tidak
     * diam-diam dihidupkan kembali.
     */
    public function test_endpoint_dump_seluruh_data_store_sudah_dihapus(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/all-data')->assertNotFound();
        $this->getJson('/api/all-data/summary')->assertNotFound();
    }

    public function test_endpoint_direktori_pengguna_tetap_berfungsi_setelah_pindah_ke_controller(): void
    {
        $this->actingAsAdmin();

        User::factory()->create([
            'username'   => 'auditor1',
            'name'       => 'Auditor Satu',
            'role'       => 'h1',
            'unit_usaha' => 'CSC TBH',
        ]);

        $this->getJson('/api/users/names')
            ->assertOk()
            ->assertJsonStructure(['data' => [['label']]]);

        $this->getJson('/api/users/options')
            ->assertOk()
            ->assertJsonStructure(['data' => [['username', 'label']]]);

        $this->getJson('/api/users/unit-usaha-by-role?role=h1')
            ->assertOk()
            ->assertJsonPath('data.0', 'CSC TBH');
    }
}
