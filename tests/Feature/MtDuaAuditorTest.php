<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanMt;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Satu SPT, dua auditor yang membagi mekanik.
 *
 * Tiap perubahan kecil di tab MT mengirim SELURUH isi layar auditor itu. Dulu
 * kiriman itu menimpa data_json apa adanya, jadi yang menyimpan belakangan
 * menulis dari salinan yang belum memuat pekerjaan rekannya: dari 6 mekanik
 * yang diperiksa berdua, cuma 3 yang tersisa.
 */
class MtDuaAuditorTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0460/01/09/2026/SPT-IAT', 'cabang' => 'CSC UJT',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);
        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'mt',
            'nama_auditor' => 'Auditor Uji', 'nama_auditee' => 'Auditee Uji',
        ]);
    }

    public function test_enam_mekanik_dari_dua_auditor_tersimpan_semua(): void
    {
        $this->sebagai('auditor1');
        $this->simpan($this->entri(['RIZAL EFENDI', 'DIKA RISKI HERMANDA', 'M. FAISAL RIDHO']), [])->assertOk();

        // Auditor 2 memuat layarnya SEBELUM rekannya menyimpan: layarnya cuma
        // berisi tiga mekanik bagiannya sendiri.
        $this->sebagai('auditor2');
        $this->simpan($this->entri(['AHMAD FAUZI', 'BUDI SANTOSO', 'CANDRA WIJAYA']), [])->assertOk();

        $this->assertSame(
            ['AHMAD FAUZI', 'BUDI SANTOSO', 'CANDRA WIJAYA', 'DIKA RISKI HERMANDA', 'M. FAISAL RIDHO', 'RIZAL EFENDI'],
            $this->mekanikTersimpan(),
            'Keenam mekanik harus tersimpan, bukan cuma tiga yang terakhir menyimpan.'
        );
    }

    public function test_balasan_membawa_gabungannya_supaya_layar_ikut_lengkap(): void
    {
        $this->sebagai('auditor1');
        $this->simpan($this->entri(['RIZAL EFENDI']), []);

        $this->sebagai('auditor2');
        $res = $this->simpan($this->entri(['AHMAD FAUZI']), [])->assertOk();

        $nama = array_column($res->json('data.data.entries'), 'mekanik');
        sort($nama);
        $this->assertSame(['AHMAD FAUZI', 'RIZAL EFENDI'], $nama);
    }

    public function test_isi_tool_mekanik_rekan_tidak_ikut_berubah(): void
    {
        $this->sebagai('auditor1');
        $punyaRekan = $this->entri(['RIZAL EFENDI']);
        $punyaRekan[0]['bagus'] = ['Kunci Ring 17 x 19', 'Tang Buaya'];
        $punyaRekan[0]['hilang'] = ['Kunci Pas Ring 10'];
        $this->simpan($punyaRekan, []);

        $this->sebagai('auditor2');
        $this->simpan($this->entri(['AHMAD FAUZI']), []);

        $rizal = collect($this->dataTersimpan()['entries'])->firstWhere('mekanik', 'RIZAL EFENDI');
        $this->assertSame(['Kunci Ring 17 x 19', 'Tang Buaya'], $rizal['bagus']);
        $this->assertSame(['Kunci Pas Ring 10'], $rizal['hilang']);
    }

    // ── Menghapus harus tetap bisa ───────────────────────────────────────────

    public function test_mekanik_yang_dihapus_auditornya_sendiri_memang_terhapus(): void
    {
        $this->sebagai('auditor1');
        $this->simpan($this->entri(['RIZAL EFENDI', 'DIKA RISKI HERMANDA']), []);

        // Layarnya mengenal dua entri itu, lalu satu dihapus.
        $this->simpan(
            $this->entri(['RIZAL EFENDI']),
            ['RIZAL EFENDI|baru', 'DIKA RISKI HERMANDA|baru']
        )->assertOk();

        $this->assertSame(['RIZAL EFENDI'], $this->mekanikTersimpan());
    }

    public function test_menghapus_tidak_ikut_membuang_mekanik_rekan_yang_belum_terlihat(): void
    {
        $this->sebagai('auditor1');
        $this->simpan($this->entri(['RIZAL EFENDI', 'DIKA RISKI HERMANDA']), []);

        $this->sebagai('auditor2');
        $this->simpan($this->entri(['AHMAD FAUZI']), []);

        // Auditor 1 menghapus DIKA. Layarnya tidak pernah melihat AHMAD.
        $this->sebagai('auditor1');
        $this->simpan(
            $this->entri(['RIZAL EFENDI']),
            ['RIZAL EFENDI|baru', 'DIKA RISKI HERMANDA|baru']
        )->assertOk();

        $this->assertSame(['AHMAD FAUZI', 'RIZAL EFENDI'], $this->mekanikTersimpan());
    }

    public function test_kiriman_lama_tanpa_daftar_dikenal_tidak_menghapus_apa_pun(): void
    {
        $this->sebagai('auditor1');
        $this->simpan($this->entri(['RIZAL EFENDI', 'DIKA RISKI HERMANDA']), []);

        // Tab yang belum dimuat ulang setelah pembaruan: tidak mengirim "dikenal".
        $this->postJson('/api/audit-detail/mt', [
            'planAuditId' => $this->plan->id,
            'data'        => ['entries' => $this->entri(['RIZAL EFENDI']), 'mekanikSelectedJenis' => []],
        ])->assertOk();

        $this->assertSame(['DIKA RISKI HERMANDA', 'RIZAL EFENDI'], $this->mekanikTersimpan());
    }

    public function test_penulisan_berada_di_dalam_transaksi(): void
    {
        $this->sebagai('auditor1');

        // Yang dijaga: PEMBACAAN isi lama sudah berada di dalam transaksi. Itu
        // syarat penguncian barisnya berlaku — kalau dibaca di luar transaksi,
        // dua permintaan tetap bisa membaca isi yang sama lalu saling menimpa.
        $dasar = DB::transactionLevel();
        $saatMembaca = null;
        DB::listen(function ($q) use (&$saatMembaca) {
            if ($saatMembaca === null && preg_match('/select .*from ["`]?pemeriksaan_mt/i', $q->sql)) {
                $saatMembaca = DB::transactionLevel();
            }
        });

        $this->simpan($this->entri(['RIZAL EFENDI']), [])->assertOk();

        $this->assertNotNull($saatMembaca, 'Tidak ada pembacaan pemeriksaan_mt yang terpantau.');
        $this->assertGreaterThan($dasar, $saatMembaca,
            'Isi lama harus dibaca DI DALAM transaksi; kalau tidak, penguncian barisnya tidak berlaku '
            . 'dan dua auditor yang menyimpan berbarengan bisa saling menimpa.');
    }

    // ── Bantuan ──────────────────────────────────────────────────────────────

    private function sebagai(string $nama): void
    {
        $user = User::query()->where('username', $nama)->first()
            ?? User::factory()->create(['role' => 'auditor', 'username' => $nama]);

        Sanctum::actingAs($user);
    }

    /** @param array<int,string> $mekanik */
    private function entri(array $mekanik): array
    {
        return array_map(fn (string $n) => [
            'mekanik' => $n, 'jenis' => 'baru',
            'bagus' => [], 'rusak' => [], 'skAudit' => [], 'hilang' => [],
        ], $mekanik);
    }

    private function simpan(array $entries, array $dikenal)
    {
        return $this->postJson('/api/audit-detail/mt', [
            'planAuditId' => $this->plan->id,
            'dikenal'     => $dikenal,
            'data'        => [
                'entries'              => $entries,
                'mekanikSelectedJenis' => array_fill_keys(array_column($entries, 'mekanik'), 'baru'),
            ],
        ]);
    }

    private function dataTersimpan(): array
    {
        return PemeriksaanMt::query()->where('plan_audit_id', $this->plan->id)->firstOrFail()->data_json;
    }

    /** @return array<int,string> */
    private function mekanikTersimpan(): array
    {
        $nama = array_column($this->dataTersimpan()['entries'], 'mekanik');
        sort($nama);

        return $nama;
    }
}
