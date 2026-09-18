<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sapuan menyeluruh: tidak ada satu pun tab pemeriksaan yang boleh menimpa
 * versi yang belum pernah dilihat layar pengirimnya.
 *
 * Tab-tab ini menyimpan SELURUH isi layarnya sekaligus dalam satu dokumen per
 * plan audit. Satu SPT dikerjakan beberapa auditor, jadi tanpa penjaga ini
 * kiriman auditor kedua membuang pekerjaan yang pertama tanpa jejak — persis
 * yang terjadi pada pemeriksaan MT (6 mekanik diperiksa berdua, tersisa 3).
 */
class TidakAdaTimpaanBasiTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0460/01/09/2026/SPT-IAT', 'cabang' => 'CSC UJT',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running', 'tgl_plan' => '2026-09-04',
        ]);
    }

    /**
     * @return array<string,array{0:string,1:string,2:array<string,mixed>}>
     */
    public static function tabPemeriksaan(): array
    {
        return [
            'Cek Fisik'        => ['cek-fisik',        'cek-fisik',        ['data' => ['a' => 1]]],
            'Kwitansi Gantung' => ['kwitansi',         'kwitansi',         ['kwitansi' => [['no' => 'K-1']]]],
            'SMH Tarikan'      => ['smh-tarikan',      'smh-tarikan',      ['items' => [['no' => 1]]]],
            'TTP CSC'          => ['ttp-csc',          'ttp-csc',          ['items' => [['no' => 1]]]],
            'Mutasi Pembelian' => ['mutasi-pembelian', 'mutasi-pembelian', ['items' => [['no' => 1]]]],
            'TTP Gantung'      => ['ttp-gantung',      'ttp-gantung',      ['ttp' => [['no' => 1]]]],
            'Piutang CDN'      => ['piutang-cdn',      'piutang-cdn',      ['piutang' => [['no' => 1]]]],
            'Piutang Reguler'  => ['piutang-reguler',  'piutang-reguler',  ['piutang' => [['no' => 1]]]],
            'BPKB Inproses'    => ['bpkb-inproses',    'bpkb-inproses',    ['penerimaanFisik' => [['no' => 1]]]],
        ];
    }

    #[DataProvider('tabPemeriksaan')]
    public function test_kiriman_dari_salinan_basi_ditolak(string $rute, string $tool, array $isi): void
    {
        $this->auditorTerisi($tool);
        $url = "/api/audit-detail/{$rute}";

        // Auditor 1 memuat layar kosong, lalu menyimpan.
        $pertama = $this->postJson($url, ['planAuditId' => $this->plan->id, 'versi' => ''] + $isi)->assertOk();
        $versi = $pertama->json('data.updatedAt');
        $this->assertNotEmpty($versi, 'Balasan harus membawa versi supaya layar bisa mengingatnya.');

        // Auditor 2 memuat layarnya SEBELUM itu — versinya masih kosong.
        $this->postJson($url, ['planAuditId' => $this->plan->id, 'versi' => ''] + $isi)
            ->assertStatus(409)
            ->assertJson(['stale' => true]);

        // Dengan versi terbaru di tangan, menyimpan berjalan normal.
        $this->postJson($url, ['planAuditId' => $this->plan->id, 'versi' => $versi] + $isi)->assertOk();
    }

    #[DataProvider('tabPemeriksaan')]
    public function test_pembacaan_isi_lama_berada_di_dalam_transaksi(string $rute, string $tool, array $isi): void
    {
        $this->auditorTerisi($tool);
        $url = "/api/audit-detail/{$rute}";
        $this->postJson($url, ['planAuditId' => $this->plan->id] + $isi)->assertOk();

        $dasar = DB::transactionLevel();
        $saatMembaca = null;
        DB::listen(function ($q) use (&$saatMembaca) {
            // pemeriksaan_auditors dibaca oleh penjaga Nama Auditor/Auditee
            // sebelum transaksinya dibuka — bukan itu yang sedang diukur.
            if ($saatMembaca === null
                && preg_match('/select .*from ["`]?(pemeriksaan_\w+)/i', $q->sql, $m)
                && $m[1] !== 'pemeriksaan_auditors') {
                $saatMembaca = DB::transactionLevel();
            }
        });

        $this->postJson($url, ['planAuditId' => $this->plan->id] + $isi)->assertOk();

        $this->assertNotNull($saatMembaca);
        $this->assertGreaterThan($dasar, $saatMembaca,
            'Isi lama harus dibaca di dalam transaksi supaya penguncian barisnya berlaku.');
    }

    public function test_pemeriksaan_kas_juga_menolak_kiriman_basi(): void
    {
        // Rutenya beda bentuk (store/update ala REST), jadi diuji terpisah.
        $this->auditorTerisi('kas');
        $isi = ['plan_audit_id' => $this->plan->id, 'nama_pos' => 'Pemeriksaan Kas'];

        $pertama = $this->postJson('/api/audit-detail/kas', $isi + ['versi' => ''])->assertSuccessful();
        $versi = $pertama->json('data.updated_at');
        $this->assertNotEmpty($versi);

        $this->postJson('/api/audit-detail/kas', $isi + ['versi' => ''])
            ->assertStatus(409)
            ->assertJson(['stale' => true]);

        $id = $pertama->json('data.id');
        $this->putJson("/api/audit-detail/kas/{$id}", ['nama_pos' => 'Pemeriksaan Kas', 'versi' => 'basi'])
            ->assertStatus(409)
            ->assertJson(['stale' => true]);
    }

    public function test_kiriman_tanpa_versi_tetap_dilayani(): void
    {
        // Tab lama yang belum dimuat ulang setelah pembaruan: jangan diputus di
        // tengah audit, walau penjaganya jadi tidak berlaku untuk tab itu.
        $this->auditorTerisi('cek-fisik');
        $this->postJson('/api/audit-detail/cek-fisik', ['planAuditId' => $this->plan->id, 'data' => ['a' => 1]])->assertOk();
        $this->postJson('/api/audit-detail/cek-fisik', ['planAuditId' => $this->plan->id, 'data' => ['a' => 2]])->assertOk();
    }

    private function auditorTerisi(string $tool): void
    {
        PemeriksaanAuditor::query()->firstOrCreate(
            ['plan_audit_id' => $this->plan->id, 'tool' => $tool],
            ['nama_auditor' => 'Auditor Uji', 'nama_auditee' => 'Auditee Uji']
        );
    }
}
