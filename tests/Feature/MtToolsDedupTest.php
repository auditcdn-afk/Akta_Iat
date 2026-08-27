<?php

namespace Tests\Feature;

use App\Models\DbMt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * db_mt (katalog tool MT) kadang punya baris ganda untuk nama tool yang sama
 * (mis. hasil import Excel dengan baris terduplikasi). Tanpa dedup, tool itu
 * ikut ke-auto-isi 2x ke kategori Bagus saat mekanik baru dibuka pertama kali,
 * dan salah satu duplikatnya tetap "available" untuk dipilih di kategori lain
 * (Rusak/SK Audit/Hilang) walau sekilas sudah ada chip-nya di Bagus.
 */
class MtToolsDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_tools_endpoint_dedup_nama_yang_sama(): void
    {
        DbMt::create(['nomor' => 1, 'kode_peralatan' => 'MT1', 'nama_peralatan' => 'Kunci Ring 8 x 9', 'nama_singkat' => 'Kunci Ring 8 x 9', 'jenis' => 'MT Baru']);
        // Baris duplikat: nama sama persis, kode berbeda (skenario nyata: hasil import 2x).
        DbMt::create(['nomor' => 2, 'kode_peralatan' => 'MT2', 'nama_peralatan' => 'Kunci Ring 8 x 9', 'nama_singkat' => 'Kunci Ring 8 x 9', 'jenis' => 'MT Baru']);
        // Duplikat dengan variasi spasi/kapital — harus tetap kena dedup.
        DbMt::create(['nomor' => 3, 'kode_peralatan' => 'MT3', 'nama_peralatan' => '  kunci ring 8 X 9 ', 'nama_singkat' => 'Kunci Ring 8 x 9', 'jenis' => 'MT Baru']);
        DbMt::create(['nomor' => 4, 'kode_peralatan' => 'MT4', 'nama_peralatan' => 'Tang Buaya', 'nama_singkat' => 'Tang Buaya', 'jenis' => 'MT Baru']);

        $res = $this->getJson('/api/audit-detail/mt/tools?jenis=baru')->assertOk();
        $names = collect($res->json('data'))->pluck('nama')->all();

        $this->assertCount(2, $names);
        $this->assertContains('Kunci Ring 8 x 9', $names);
        $this->assertContains('Tang Buaya', $names);
    }
}
