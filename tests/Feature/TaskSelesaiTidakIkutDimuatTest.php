<?php

namespace Tests\Feature;

use App\Models\AuditTask;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Halaman Task adalah tempat persinggahan pekerjaan yang masih berjalan, jadi
 * task selesai tidak ikut terunduh setiap halaman dibuka — riwayat selesai
 * bertambah terus dan itu bagian terbesar dari daftar admin.
 *
 * Yang penting: datanya TIDAK hilang. Admin tetap bisa memanggilnya kapan saja
 * lewat include_done=1 (filter status "Selesai" di halaman).
 */
class TaskSelesaiTidakIkutDimuatTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0501/01/09/2026/SPT-IAT',
            'cabang'      => 'SO ALB',
            'jenis_audit' => 'Audit Kas',
            'status'      => 'running',
            'kepala_tim'  => 'Abdul Aziz',
            'tim'         => [],
        ]);

        foreach ([['Abdul Aziz', 'todo'], ['Salim', 'done']] as [$nama, $status]) {
            AuditTask::query()->create([
                'plan_audit_id' => $this->plan->id,
                'judul'         => 'Audit Kas - SO ALB',
                'kategori'      => 'Audit Kas',
                'assigned_to'   => $nama,
                'priority'      => 'normal',
                'status'        => $status,
            ]);
        }
    }

    private function statusTask(array $rows): array
    {
        return collect($rows)->pluck('status')->sort()->values()->all();
    }

    public function test_admin_tidak_menerima_task_selesai_secara_bawaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $rows = $this->getJson('/api/tasks')->assertOk()->json('data');

        // Dua todo: petugasnya sendiri + task cabang yang dibuat otomatis
        // karena plan-nya sudah running. Yang penting: tidak ada 'done'.
        $this->assertSame(['todo', 'todo'], $this->statusTask($rows));
    }

    public function test_admin_tetap_bisa_memanggil_task_selesai(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $rows = $this->getJson('/api/tasks?include_done=1')->assertOk()->json('data');

        $this->assertSame(['done', 'todo', 'todo'], $this->statusTask($rows));
    }

    public function test_data_task_selesai_tetap_utuh_di_database(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/tasks')->assertOk();

        // Tidak ditampilkan bukan berarti dihapus.
        $this->assertDatabaseHas('audit_tasks', [
            'plan_audit_id' => $this->plan->id,
            'assigned_to'   => 'Salim',
            'status'        => 'done',
        ]);
        $this->assertSame(1, AuditTask::where('plan_audit_id', $this->plan->id)->where('status', 'done')->count());
    }

    public function test_role_lain_tetap_tidak_melihat_task_selesai_walau_diminta(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'name' => 'Salim']));

        $rows = $this->getJson('/api/tasks?include_done=1')->assertOk()->json('data');

        // Auditor Salim hanya punya task yang sudah selesai — dan itu memang
        // tidak pernah ditampilkan untuk role selain admin.
        $this->assertSame([], $this->statusTask($rows));
    }
}
