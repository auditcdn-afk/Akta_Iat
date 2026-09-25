<?php

namespace Tests\Feature;

use App\Models\BuPerformance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BU Performance: rekap seluruh unit usaha tempatnya di menu BU Performance,
 * bukan di dalam satu pemeriksaan -- dan daftarnya dibaca per bulan.
 *
 * Bulannya disimpan sebagai TEKS ("Januari 2026"), jadi orderBy('bulan')
 * mengurutkannya menurut abjad: "April 2026" jatuh sebelum "Januari 2026" dan
 * "Desember 2025" menyelinap di tengah tahun 2026.
 */
class BuPerformanceUrutBulanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'unit_usaha' => 'HO']));

        foreach ([
            ['Desember 2025', 'POS TBN'],
            ['Januari 2026', 'POS TBN'],
            ['April 2026', 'SO PGR'],
            ['September 2026', 'WHS PART AVIAN'],
            ['September 2026', 'SO PGR'],
        ] as [$bulan, $unit]) {
            BuPerformance::query()->create([
                'bulan'      => $bulan,
                'unit_usaha' => $unit,
                'auditor'    => 'Abdul Aziz',
                'penilaian'  => [['pic' => 'A', 'jabatan' => 'B', 'uraian' => 'C']],
            ]);
        }
    }

    public function test_kunci_urut_bulan_menerjemahkan_nama_bulan(): void
    {
        $this->assertSame('2026-01', BuPerformance::kunciUrutBulan('Januari 2026'));
        $this->assertSame('2025-12', BuPerformance::kunciUrutBulan('Desember 2025'));
        $this->assertSame('2026-09', BuPerformance::kunciUrutBulan('september 2026'), 'huruf besar-kecil diabaikan');

        // Yang tidak dikenali jatuh di ujung, tidak menyelinap di tengah.
        $this->assertStringStartsWith('9999', BuPerformance::kunciUrutBulan('Q3 2026'));
        $this->assertStringStartsWith('9999', BuPerformance::kunciUrutBulan(''));
    }

    public function test_daftar_bulan_urut_kronologis_terbaru_dulu(): void
    {
        $bulans = $this->getJson('/api/bu-performance/bulans')->assertOk()->json('data');

        $this->assertSame(
            ['September 2026', 'April 2026', 'Januari 2026', 'Desember 2025'],
            $bulans,
            'kalau diurutkan menurut abjad hasilnya April, Desember 2025, Januari, September'
        );
    }

    public function test_bulan_terakhir_ada_di_urutan_pertama(): void
    {
        // Layar memakai pilihan pertama sebagai bulan bawaan saat halaman dibuka.
        $this->assertSame(
            'September 2026',
            $this->getJson('/api/bu-performance/bulans')->json('data.0')
        );
    }

    public function test_daftar_data_urut_kronologis_terbaru_dulu(): void
    {
        $urut = collect($this->getJson('/api/bu-performance')->assertOk()->json('data'))
            ->pluck('bulan')->unique()->values()->all();

        $this->assertSame(['September 2026', 'April 2026', 'Januari 2026', 'Desember 2025'], $urut);
    }

    public function test_bisa_disaring_ke_satu_unit_usaha_saja(): void
    {
        // Dipakai tab BU Performance di dalam pemeriksaan: hanya unit usaha
        // yang sedang diperiksa, bukan seluruh unit usaha.
        $rows = $this->getJson('/api/bu-performance?unit_usaha=' . urlencode('WHS PART AVIAN'))
            ->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('WHS PART AVIAN', $rows[0]['unitUsaha']);
    }

    public function test_bisa_disaring_ke_satu_bulan_saja(): void
    {
        $rows = $this->getJson('/api/bu-performance?bulan=' . urlencode('September 2026'))
            ->assertOk()->json('data');

        $this->assertCount(2, $rows);
        $this->assertSame(['September 2026'], collect($rows)->pluck('bulan')->unique()->all());
    }
}
