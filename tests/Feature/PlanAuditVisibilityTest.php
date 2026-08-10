<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menu Plan Audit / Audit / Task dkk semuanya membaca daftar plan lewat
 * GET /api/plans (PlanAuditController::index). Sebelumnya role 'auditor'
 * ikut dianggap "HO" sehingga melihat SELURUH plan, bukan hanya plan yang
 * mereka terlibat sebagai kepala tim atau anggota tim — padahal komentar di
 * kode itu sendiri sudah menyatakan niat sebaliknya ("Auditor HO non-cabang:
 * hanya plan yang mereka terlibat sebagai tim").
 */
class PlanAuditVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_hanya_melihat_plan_dimana_ia_kepala_tim_atau_anggota_tim(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'username'     => 'budi',
            'name'         => 'Budi Santoso',
            'display_name' => 'Budi Santoso',
            'role'         => 'auditor',
            'unit_usaha'   => 'HO',
        ]));

        $planKepalaTim = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
            'kepala_tim' => 'Budi Santoso',
        ]);
        $planAnggotaTim = PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT', 'cabang' => 'CSC B',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
            'kepala_tim' => 'Rizki Akhiruddin', 'tim' => ['Rizki Akhiruddin', 'Budi Santoso'],
        ]);
        $planOrangLain = PlanAudit::query()->create([
            'no_spt' => '0003/01/01/2026/SPT-IAT', 'cabang' => 'CSC C',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
            'kepala_tim' => 'Rizki Akhiruddin', 'tim' => ['Rizki Akhiruddin'],
        ]);

        $response = $this->getJson('/api/plans');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($planKepalaTim->id));
        $this->assertTrue($ids->contains($planAnggotaTim->id));
        $this->assertFalse($ids->contains($planOrangLain->id));
        $this->assertCount(2, $ids);
    }

    #[DataProvider('hoRolesYangTetapMelihatSemua')]
    public function test_role_ho_selain_auditor_tetap_melihat_semua_plan(string $role): void
    {
        Sanctum::actingAs(User::factory()->create([
            'username' => 'ho-user',
            'role'     => $role,
        ]));

        PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
            'kepala_tim' => 'Orang Lain', 'tim' => ['Orang Lain'],
        ]);
        PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT', 'cabang' => 'CSC B',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
            'kepala_tim' => 'Orang Lain Lagi', 'tim' => ['Orang Lain Lagi'],
        ]);

        $response = $this->getJson('/api/plans');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public static function hoRolesYangTetapMelihatSemua(): array
    {
        return [
            ['admin'],
            ['manajer'],
            ['koordinator'],
            ['coo'],
        ];
    }

    public function test_role_cabang_tetap_hanya_melihat_plan_unit_usahanya_sendiri(): void
    {
        // Perilaku lama untuk role cabang (h1/h2/unit/dst) tidak boleh berubah
        // sedikit pun oleh perbaikan ini.
        Sanctum::actingAs(User::factory()->create([
            'username'   => 'cabang-a',
            'role'       => 'h1',
            'unit_usaha' => 'CSC A',
        ]));

        $planUnitSendiri = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'CSC A',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
        ]);
        $planUnitLain = PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT', 'cabang' => 'CSC B',
            'jenis_audit' => 'Audit', 'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/plans');
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($planUnitSendiri->id));
        $this->assertFalse($ids->contains($planUnitLain->id));
    }
}
