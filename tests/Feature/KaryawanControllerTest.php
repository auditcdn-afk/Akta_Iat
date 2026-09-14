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

    // ── Nomor HP ─────────────────────────────────────────────────────────

    public function test_nomor_hp_tersimpan_dan_ikut_di_balasan_api(): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $this->postJson('/api/karyawan', [
            'nama' => 'Budi Santoso', 'jabatan' => 'Sales', 'no_hp' => '0812-3456-7890',
        ])->assertStatus(201)->assertJsonPath('data.noHp', '0812-3456-7890');

        $this->assertDatabaseHas('karyawans', ['nama' => 'Budi Santoso', 'no_hp' => '0812-3456-7890']);

        $this->getJson('/api/karyawan')->assertOk()->assertJsonPath('data.0.noHp', '0812-3456-7890');
    }

    public function test_nomor_hp_boleh_dikosongkan(): void
    {
        // Data karyawan lama tidak punya nomor — memaksanya terisi akan
        // membuat seluruh baris lama tidak bisa disimpan lagi.
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $this->postJson('/api/karyawan', ['nama' => 'Tanpa Nomor', 'jabatan' => 'Mekanik'])
            ->assertStatus(201)
            ->assertJsonPath('data.noHp', null);

        $this->assertDatabaseHas('karyawans', ['nama' => 'Tanpa Nomor', 'no_hp' => null]);
    }

    public function test_nomor_hp_berisi_spasi_saja_disimpan_sebagai_kosong(): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $this->postJson('/api/karyawan', ['nama' => 'Spasi', 'jabatan' => 'Sales', 'no_hp' => '   '])
            ->assertStatus(201);

        $this->assertDatabaseHas('karyawans', ['nama' => 'Spasi', 'no_hp' => null]);
    }

    /** @return array<string,array{0:string}> */
    public static function ragamPenulisanNomor(): array
    {
        return [
            'awalan nol'      => ['081234567890'],
            'awalan +62'      => ['+62 812 3456 7890'],
            'dengan hubung'   => ['0812-3456-7890'],
            'nomor kantor'    => ['(061) 456789'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ragamPenulisanNomor')]
    public function test_nomor_diterima_apa_adanya_tanpa_dipaksa_satu_format(string $nomor): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $this->postJson('/api/karyawan', ['nama' => 'Uji', 'jabatan' => 'Sales', 'no_hp' => $nomor])
            ->assertStatus(201)
            ->assertJsonPath('data.noHp', $nomor);
    }

    public function test_nomor_lebih_dari_30_karakter_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'unit', 'unit_usaha' => 'SO ALB']);
        Sanctum::actingAs($user);

        $this->postJson('/api/karyawan', [
            'nama' => 'Kepanjangan', 'jabatan' => 'Sales', 'no_hp' => str_repeat('9', 31),
        ])->assertStatus(422)->assertJsonValidationErrors('no_hp');
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
