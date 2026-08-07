<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Perlengkapan di luar SMH" mengambil Saldo dari data onhand SMH yang sudah
 * diimpor, disilangkan dengan db_perlengkapan lewat kode tipe motor (5 huruf
 * pertama no mesin).
 *
 * Saldo harus sudah terisi begitu onhand diimpor — tidak menunggu unit-unitnya
 * diperiksa fisik satu per satu.
 */
class PerlengkapanSaldoTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor-pl',
            'role'     => 'auditor',
        ]));

        DbUnitUsaha::query()->create([
            'unit_usaha' => 'CVSK H1',
            'wilayah'    => 'riau',
            'jenis'      => 'H1',
        ]);

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0424/28/07/2026/SPT-IAT',
            'cabang'      => 'CVSK H1',
            'jenis_audit' => 'Audit Full SO',
            'status'      => 'running',
        ]);

        // Tipe KCD2E wajib punya dua perlengkapan; KC03E hanya satu.
        DbPerlengkapan::query()->create([
            'kode'       => 'KCD2E',
            'wilayah'    => 'riau',
            'nama'       => 'Revo',
            'keterangan' => 'Kaca Spion Revo, Helm Revo',
        ]);

        DbPerlengkapan::query()->create([
            'kode'       => 'KC03E',
            'wilayah'    => 'riau',
            'nama'       => 'Beat',
            'keterangan' => 'Kaca Spion Beat',
        ]);
    }

    /** @param array<int, array{no_mesin: string, status_fisik?: string|null, perlengkapan_json?: array|null}> $rows */
    private function importOnhand(array $rows): PemeriksaanSmh
    {
        $pmx = PemeriksaanSmh::query()->create([
            'plan_audit_id' => $this->plan->id,
            'no_spt'        => $this->plan->no_spt,
            'cabang'        => $this->plan->cabang,
            'total_unit'    => count($rows),
        ]);

        foreach ($rows as $row) {
            $pmx->items()->create([
                'no_mesin'          => $row['no_mesin'],
                'status_fisik'      => $row['status_fisik'] ?? null,
                'perlengkapan_json' => $row['perlengkapan_json'] ?? null,
            ]);
        }

        return $pmx;
    }

    // Sejak Nama Auditor/Auditee wajib per (plan, tool), simpan/update di tool
    // manapun (termasuk perlengkapan) ditolak sampai widget-nya terisi — lihat
    // RequiresAuditorAuditee dan PemeriksaanAuditorTest.php.
    private function fillAuditorAuditee(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'perlengkapan',
            'nama_auditee'  => 'Budi (Kepala Cabang)',
        ])->assertOk();
    }

    /** @return array<string, array<string, mixed>> */
    private function summaryByNama(): array
    {
        $response = $this->getJson(
            '/api/audit-detail/perlengkapan/smh-summary?plan_audit_id=' . $this->plan->id
        );

        $response->assertOk();

        return collect($response->json('data'))->keyBy('nama')->all();
    }

    public function test_saldo_terisi_dari_onhand_walau_belum_ada_unit_yang_diperiksa_fisik(): void
    {
        // Tiga unit KCD2E, dua unit KC03E — semuanya baru diimpor, belum diperiksa.
        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089'],
            ['no_mesin' => 'KCD2E 1053090'],
            ['no_mesin' => 'KCD2E 1053091'],
            ['no_mesin' => 'KC03E 1012240'],
            ['no_mesin' => 'KC03E 1012265'],
        ]);

        $summary = $this->summaryByNama();

        // Inilah inti perbaikannya: sebelumnya endpoint mengembalikan array kosong
        // selama belum ada unit ber-status_fisik "ada", sehingga form menampilkan
        // Saldo 0 untuk semua jenis.
        $this->assertArrayHasKey('Kaca Spion Revo', $summary);
        $this->assertArrayHasKey('Helm Revo', $summary);
        $this->assertArrayHasKey('Kaca Spion Beat', $summary);

        $this->assertSame(3, $summary['Kaca Spion Revo']['totalOnhand']);
        $this->assertSame(3, $summary['Helm Revo']['totalOnhand']);
        $this->assertSame(2, $summary['Kaca Spion Beat']['totalOnhand']);

        // Belum ada yang diperiksa, jadi belum ada yang tercatat "ada".
        $this->assertSame(0, $summary['Kaca Spion Revo']['ada']);
        $this->assertSame(0, $summary['Kaca Spion Beat']['ada']);
    }

    public function test_saldo_berkurang_seiring_perlengkapan_ditemukan_saat_periksa_fisik(): void
    {
        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089', 'status_fisik' => 'ada', 'perlengkapan_json' => [
                ['nama' => 'Kaca Spion Revo', 'ada' => true],
                ['nama' => 'Helm Revo', 'ada' => false],
            ]],
            ['no_mesin' => 'KCD2E 1053090', 'status_fisik' => 'ada', 'perlengkapan_json' => [
                ['nama' => 'Kaca Spion Revo', 'ada' => true],
                ['nama' => 'Helm Revo', 'ada' => true],
            ]],
            ['no_mesin' => 'KCD2E 1053091'],
        ]);

        $summary = $this->summaryByNama();

        // Saldo yang dipakai form = totalOnhand - ada.
        $this->assertSame(3, $summary['Kaca Spion Revo']['totalOnhand']);
        $this->assertSame(2, $summary['Kaca Spion Revo']['ada']);

        $this->assertSame(3, $summary['Helm Revo']['totalOnhand']);
        $this->assertSame(1, $summary['Helm Revo']['ada']);
    }

    public function test_daftar_jenis_mengikuti_tipe_motor_yang_benar_benar_ada_di_onhand(): void
    {
        // Hanya tipe KCD2E yang diimpor — jenis milik KC03E tidak boleh ikut muncul.
        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089'],
            ['no_mesin' => 'KCD2E 1053090'],
        ]);

        $response = $this->getJson(
            '/api/audit-detail/perlengkapan/jenis?plan_audit_id=' . $this->plan->id
        );

        $response->assertOk();
        $jenis = $response->json('data');

        $this->assertContains('Kaca Spion Revo', $jenis);
        $this->assertContains('Helm Revo', $jenis);
        $this->assertNotContains('Kaca Spion Beat', $jenis);
    }

    public function test_wilayah_dicocokkan_tanpa_peduli_huruf_besar_kecil(): void
    {
        // db_unit_usaha menyimpan wilayah huruf besar ("RIAU") sementara importir
        // perlengkapan menyimpannya huruf kecil ("riau") — kombinasi yang memang
        // terjadi di produksi.
        DbUnitUsaha::query()->where('unit_usaha', 'CVSK H1')->update(['wilayah' => 'RIAU']);

        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089'],
            ['no_mesin' => 'KCD2E 1053090'],
        ]);

        $summary = $this->summaryByNama();

        $this->assertSame(2, $summary['Kaca Spion Revo']['totalOnhand']);
        $this->assertSame(2, $summary['Helm Revo']['totalOnhand']);
    }

    public function test_saldo_tersimpan_diturunkan_server_bukan_dari_kiriman_klien(): void
    {
        $this->fillAuditorAuditee();

        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089'],
            ['no_mesin' => 'KCD2E 1053090'],
            ['no_mesin' => 'KCD2E 1053091'],
        ]);

        // Klien mengirim saldo 0 (persis gejala bug: form menampilkan 0).
        // Server harus mengabaikannya dan memakai angka dari onhand.
        $response = $this->postJson('/api/audit-detail/perlengkapan', [
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Kaca Spion Revo',
            'saldo'              => 0,
            'fisik'              => 2,
        ]);

        $response->assertCreated();
        $this->assertSame(3.0, (float) $response->json('data.saldo'));
        // selisih = fisik - saldo = 2 - 3
        $this->assertSame(-1.0, (float) $response->json('data.selisih'));

        // Nilai yang dibaca ReportPdfController pun ikut benar.
        $this->assertDatabaseHas('pemeriksaan_perlengkapan', [
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Kaca Spion Revo',
            'saldo'              => 3,
        ]);
    }

    public function test_baris_lama_dengan_saldo_salah_terkoreksi_saat_diupdate(): void
    {
        $this->fillAuditorAuditee();

        $this->importOnhand([
            ['no_mesin' => 'KCD2E 1053089'],
            ['no_mesin' => 'KCD2E 1053090'],
        ]);

        // Baris yang terlanjur tersimpan saat bug masih aktif.
        $rec = \App\Models\PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => 'Kaca Spion Revo',
            'saldo'              => 0,
            'fisik'              => 1,
            'selisih'            => 1,
        ]);

        $response = $this->putJson("/api/audit-detail/perlengkapan/{$rec->id}", ['fisik' => 1]);

        $response->assertOk();
        $this->assertSame(2.0, (float) $response->json('data.saldo'));
        $this->assertSame(-1.0, (float) $response->json('data.selisih'));
    }

    public function test_tanpa_data_onhand_daftar_jenis_jatuh_ke_db_perlengkapan_wilayah(): void
    {
        $response = $this->getJson(
            '/api/audit-detail/perlengkapan/jenis?plan_audit_id=' . $this->plan->id
        );

        $response->assertOk();
        $jenis = $response->json('data');

        // Tidak ada onhand sama sekali: tetap tampilkan katalog wilayahnya supaya
        // auditor masih bisa mencatat perlengkapan secara manual.
        $this->assertContains('Kaca Spion Revo', $jenis);
        $this->assertContains('Kaca Spion Beat', $jenis);
    }
}
