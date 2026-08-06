<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use App\Support\DataKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_ringkasan_data_store_hanya_mengirim_jumlah_key(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/all-data/summary');

        $response->assertOk()->assertExactJson([
            'ok'    => true,
            'count' => count(DataKeys::all()),
        ]);
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
