<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Data karyawan per unit usaha: hanya akun unit usaha yang bersangkutan
 * (atau admin) yang boleh menambah/menghapus datanya sendiri -- unit lain
 * tidak boleh menambah/menghapus atas nama cabang lain. HO roles
 * (manajer/auditor/dst.) boleh melihat semua unit tapi tidak menambah/hapus.
 */
class KaryawanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_usaha_bisa_menambah_karyawan_untuk_dirinya_sendiri(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/karyawan', [
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sales',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('karyawans', [
            'unit_usaha' => 'SO ALB',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sales',
        ]);
        Storage::disk('public')->assertExists(Karyawan::first()->photo_path);
    }

    public function test_unit_usaha_tidak_bisa_menambah_atas_nama_cabang_lain(): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/karyawan', [
            'unit_usaha' => 'SO BDS',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sales',
        ]);

        $response->assertStatus(201);
        // unit_usaha dari body diabaikan untuk non-admin -- tetap masuk ke unit sendiri.
        $this->assertDatabaseHas('karyawans', ['unit_usaha' => 'SO ALB', 'nama' => 'Budi Santoso']);
        $this->assertDatabaseMissing('karyawans', ['unit_usaha' => 'SO BDS', 'nama' => 'Budi Santoso']);
    }

    public function test_unit_usaha_tidak_bisa_menghapus_karyawan_cabang_lain(): void
    {
        $karyawan = Karyawan::query()->create([
            'unit_usaha' => 'SO BDS',
            'nama' => 'Karyawan Lain',
            'jabatan' => 'Sales',
        ]);
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/karyawan/{$karyawan->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('karyawans', ['id' => $karyawan->id]);
    }

    public function test_unit_usaha_bisa_menghapus_karyawan_sendiri(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('foto.jpg')->store('karyawan', 'public');
        $karyawan = Karyawan::query()->create([
            'unit_usaha' => 'SO ALB',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sales',
            'photo_path' => $path,
        ]);
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/karyawan/{$karyawan->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('karyawans', ['id' => $karyawan->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_bisa_menambah_untuk_unit_usaha_manapun(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'unit_usaha' => null]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/karyawan', [
            'unit_usaha' => 'SO BDS',
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sales',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('karyawans', ['unit_usaha' => 'SO BDS', 'nama' => 'Budi Santoso']);
    }

    public function test_ho_role_melihat_semua_unit_tapi_unit_usaha_hanya_lihat_sendiri(): void
    {
        Karyawan::query()->create(['unit_usaha' => 'SO ALB', 'nama' => 'A', 'jabatan' => 'Sales']);
        Karyawan::query()->create(['unit_usaha' => 'SO BDS', 'nama' => 'B', 'jabatan' => 'Sales']);

        $manajer = User::factory()->create(['role' => 'manajer', 'unit_usaha' => null]);
        Sanctum::actingAs($manajer);
        $response = $this->getJson('/api/karyawan');
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $unitUser = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($unitUser);
        $response = $this->getJson('/api/karyawan');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.unitUsaha', 'SO ALB');
    }
}
