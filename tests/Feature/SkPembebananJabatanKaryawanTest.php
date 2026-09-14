<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nama personil pembebanan SK diambil dari Data Karyawan unit usaha yang
 * bersangkutan, dan jabatannya ikut dari sana. Rekap "per personil" dan "per
 * jabatan" pada Grafik Beban SK dikelompokkan dari string nama/jabatan, jadi
 * penulisan yang berbeda-beda membuat satu orang terhitung sebagai beberapa
 * orang dan bebannya terpecah.
 */
class SkPembebananJabatanKaryawanTest extends TestCase
{
    use RefreshDatabase;

    private function buatSk(string $unitUsaha = 'SO ARK'): int
    {
        return DB::table('surat_keputusan')->insertGetId([
            'no_sk' => '001',
            'unit_usaha' => $unitUsaha,
            'status' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function payload(int $skId, array $personil, string $unitUsaha = 'SO ARK'): array
    {
        return [
            'surat_keputusan_id' => $skId,
            'unit_usaha' => $unitUsaha,
            'personil' => $personil + ['rincian' => [['kategori' => 'Matprom', 'nilai' => 50000]]],
        ];
    }

    public function test_jabatan_diambil_dari_data_karyawan_unit_usaha_itu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk();
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Kepala Gudang']);

        // Form mengirim jabatan kosong; server melengkapinya dari Data Karyawan.
        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'Juli']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.jabatan', 'Kepala Gudang');
    }

    public function test_jabatan_dari_data_karyawan_menang_atas_yang_dikirim_form(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk();
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Kepala Gudang']);

        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'Juli', 'jabatan' => 'kepala gdg']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.jabatan', 'Kepala Gudang');
    }

    public function test_nama_dicocokkan_tanpa_membedakan_huruf_besar_kecil(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk();
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Kepala Gudang']);

        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'JULI']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.jabatan', 'Kepala Gudang');
    }

    public function test_karyawan_unit_usaha_lain_tidak_dipakai(): void
    {
        // Nama yang sama di cabang lain bukan orang yang sama.
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk('SO ARK');
        Karyawan::query()->create(['unit_usaha' => 'POS TBN', 'nama' => 'Juli', 'jabatan' => 'Kasir']);

        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'Juli', 'jabatan' => 'Mekanik']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.jabatan', 'Mekanik');
    }

    public function test_dua_karyawan_sename_beda_jabatan_memakai_jabatan_dari_form(): void
    {
        // Tidak ada dasar untuk menebak yang mana; form-lah yang tahu karyawan
        // mana yang dipilih, jadi kiriman form yang dipakai apa adanya.
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk();
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Kepala Gudang']);
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Mekanik']);

        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'Juli', 'jabatan' => 'Mekanik']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.jabatan', 'Mekanik');
    }

    public function test_nama_di_luar_data_karyawan_tetap_bisa_disimpan(): void
    {
        // Unit yang Data Karyawan-nya belum dilengkapi tidak boleh sampai
        // terkunci tidak bisa mengisi pembebanan sama sekali.
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $sk = $this->buatSk();

        $this->postJson('/api/sk-pembebanan', $this->payload($sk, ['nama' => 'Orang Baru', 'jabatan' => 'Helper']))
            ->assertStatus(201)
            ->assertJsonPath('data.personil.0.nama', 'Orang Baru')
            ->assertJsonPath('data.personil.0.jabatan', 'Helper');
    }

    public function test_daftar_karyawan_bisa_disaring_per_unit_usaha_untuk_pilihan_personil(): void
    {
        // Sumber isi dropdown di modal Pembebanan SK.
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        Karyawan::query()->create(['unit_usaha' => 'SO ARK', 'nama' => 'Juli', 'jabatan' => 'Kepala Gudang']);
        Karyawan::query()->create(['unit_usaha' => 'POS TBN', 'nama' => 'Vicky', 'jabatan' => 'Kepala POS']);

        $this->getJson('/api/karyawan?unit_usaha=' . urlencode('SO ARK'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Juli')
            ->assertJsonPath('data.0.jabatan', 'Kepala Gudang');
    }
}
