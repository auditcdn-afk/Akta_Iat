<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Menangkap bentuk keluaran endpoint rekap dashboard apa adanya, supaya
 *  optimasi query tidak diam-diam mengubah angka grafik. */
class RekapCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRekap(): void
    {
        DB::table('surat_keputusan')->insert(['id' => 1, 'no_sk' => 'SK-1', 'created_at' => now(), 'updated_at' => now()]);

        $personil = fn(string $nama, string $jab, float $sub) => [[
            'nama' => $nama, 'jabatan' => $jab, 'subtotal' => $sub,
            'rincian' => [
                ['kategori' => 'Transport', 'nilai' => $sub / 2],
                ['kategori' => 'Penginapan', 'nilai' => $sub / 2],
            ],
        ]];

        $rows = [
            ['CSC A', 'H1', 'final', 1_500_000, '2025-03-14', $personil('Budi', 'Ketua Tim', 900_000)],
            ['CSC A', 'H1', 'draft', 500_000, '2026-01-05', $personil('Budi', 'Ketua Tim', 300_000)],
            ['CSC B', 'H2', 'final', 2_250_000, '2026-01-20', $personil('Sari', 'Anggota', 1_000_000)],
            ['CSC C', null, 'final', 750_000, '2026-07-02', $personil('Sari', 'Anggota', 250_000)],
        ];

        foreach ($rows as [$unit, $jenis, $status, $total, $tgl, $orang]) {
            DB::table('sk_pembebanan')->insert([
                'surat_keputusan_id' => 1, 'unit_usaha' => $unit, 'jenis_unit' => $jenis,
                'status' => $status, 'total_pembebanan' => $total, 'tgl_audit' => $tgl,
                'personil' => json_encode($orang), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Realisasi dinas: 2 plan, masing-masing beberapa item.
        DB::table('plan_audits')->insert([
            ['id' => 1, 'no_spt' => '0001', 'cabang' => 'CSC A', 'jenis_audit' => 'Audit', 'status' => 'done', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'no_spt' => '0002', 'cabang' => 'CSC B', 'jenis_audit' => 'Audit', 'status' => 'done', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('realisasi_dinas')->insert([
            ['id' => 1, 'plan_audit_id' => 1, 'personil' => json_encode([['nama' => 'Budi', 'jabatan' => 'Ketua Tim']]), 'created_at' => '2026-02-10 08:00:00', 'updated_at' => now()],
            ['id' => 2, 'plan_audit_id' => 2, 'personil' => json_encode([['nama' => 'Sari', 'jabatan' => 'Anggota']]), 'created_at' => '2026-05-11 08:00:00', 'updated_at' => now()],
        ]);
        DB::table('realisasi_dinas_items')->insert([
            ['realisasi_dinas_id' => 1, 'jenis_pengeluaran' => 'Transport', 'nominal' => 400_000, 'created_at' => now(), 'updated_at' => now()],
            ['realisasi_dinas_id' => 1, 'jenis_pengeluaran' => 'Penginapan', 'nominal' => 600_000, 'created_at' => now(), 'updated_at' => now()],
            ['realisasi_dinas_id' => 2, 'jenis_pengeluaran' => 'Transport', 'nominal' => 250_000, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_snapshot_rekap(): void
    {
        Sanctum::actingAs(User::factory()->create(['username' => 'snap', 'role' => 'admin']));
        $this->seedRekap();

        $out = [];
        foreach (['/api/sk-pembebanan/rekap', '/api/realisasi-dinas/rekap'] as $url) {
            $out[$url] = $this->getJson($url)->assertOk()->json();
            // Juga dengan filter aktif, supaya jalur ber-filter ikut terkunci.
            $out[$url . '?tahun=2026'] = $this->getJson($url . '?tahun=2026')->assertOk()->json();
        }

        $path = base_path('tests/Feature/__rekap_snapshot.json');
        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!file_exists($path)) {
            file_put_contents($path, $json);
            $this->markTestSkipped('Snapshot dibuat: ' . $path);
        }

        $this->assertSame(
            json_decode(file_get_contents($path), true),
            json_decode($json, true),
            'Keluaran endpoint rekap berubah dibanding snapshot.'
        );
    }
}
