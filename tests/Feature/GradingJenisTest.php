<?php

namespace Tests\Feature;

use App\Models\DbGrading;
use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Grading Audit mengambil daftar item pemeriksaannya dari master grading,
 * disaring menurut Jenis unit usaha (Cabang / Bengkel / WHS PART / WHS UNIT).
 *
 * Dilaporkan: plan "Audit Full CSC" justru memunculkan item milik SO. Dua hal
 * yang membuatnya begitu, dan keduanya diuji di sini:
 *
 *   1. Pemetaan jenis audit -> jenis grading memakai kunci 'H1'/'H2', padahal
 *      jenis_audit tidak pernah berisi itu. Pemetaannya tidak pernah cocok,
 *      jadi Jenis tidak pernah terpilih otomatis.
 *   2. Saat penyaringan tidak menemukan apa-apa, layar diam-diam menampilkan
 *      SELURUH master -- semua jenis tercampur, tanpa auditor bisa tahu.
 */
class GradingJenisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function plan(string $jenisAudit, string $cabang): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt'      => '0460/01/09/2026/SPT-IAT',
            'cabang'      => $cabang,
            'jenis_audit' => $jenisAudit,
            'status'      => 'running',
        ]);
    }

    private function masterItem(string $jenis, string $nama, string $wilayah = 'RIAU'): DbGrading
    {
        return DbGrading::query()->create([
            'id_grading'        => $jenis . '-' . $nama,
            'jenis'             => $jenis,
            'wilayah'           => $wilayah,
            'nama_pemeriksaan'  => $nama,
            'hasil_pemeriksaan' => 'Sesuai',
            'nilai'             => 1,
        ]);
    }

    /** Jenis grading harus ketahuan sendiri dari jenis audit plan-nya. */
    #[DataProvider('petaJenis')]
    public function test_jenis_grading_ditebak_dari_jenis_audit(string $jenisAudit, string $cabang, ?string $harap): void
    {
        $plan = $this->plan($jenisAudit, $cabang);

        $res = $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->assertOk();

        $this->assertSame($harap, $res->json('data.jenisGrading'),
            "\"{$jenisAudit}\" di {$cabang} seharusnya jenis grading " . ($harap ?? 'kosong'));
    }

    public static function petaJenis(): array
    {
        return [
            'Audit Full CSC -> Bengkel'      => ['Audit Full CSC', 'CSC UJT', 'Bengkel'],
            'Audit Full SO -> Cabang'        => ['Audit Full SO', 'SO UJT', 'Cabang'],
            'Warehouse PART -> WHS PART'     => ['Audit Warehouse PART', 'WHS Part KIM', 'WHS PART'],
            'Warehouse UNIT -> WHS UNIT'     => ['Audit Warehouse UNIT', 'WHS Unit KIM', 'WHS UNIT'],
            'Serah Terima Workshop -> Bengkel' => ['Audit Serah Terima Workshop Head', 'CSC PRW', 'Bengkel'],
            'Serah Terima SO Head -> Cabang' => ['Audit Serah Terima Sales Office Head', 'SO PRW', 'Cabang'],
            // Jenis audit yang netral: yang menentukan nama unit usahanya.
            'Kas + BPKB di CSC -> Bengkel'   => ['Audit Kas + BPKB', 'CSC UJT', 'Bengkel'],
            'Kas + BPKB di SO -> Cabang'     => ['Audit Kas + BPKB', 'SO UJT', 'Cabang'],
            // Benar-benar tidak bisa ditebak: dibiarkan kosong, auditor memilih sendiri.
            'Verifikasi HO -> kosong'        => ['Audit Verifikasi HO', 'HO MEDAN', null],
        ];
    }

    /** Wilayah tetap diambil dari master unit usaha seperti sebelumnya. */
    public function test_wilayah_diambil_dari_master_unit_usaha(): void
    {
        DbUnitUsaha::query()->create(['unit_usaha' => 'CSC UJT', 'wilayah' => 'RIAU', 'jenis' => 'Cabang']);
        $plan = $this->plan('Audit Full CSC', 'CSC UJT');

        $res = $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->assertOk();

        $this->assertSame('RIAU', $res->json('data.area'));
        $this->assertSame('Bengkel', $res->json('data.jenisGrading'),
            'Master unit usaha mencatat CSC sebagai "Cabang"; jenis audit harus lebih menentukan.');
    }

    /** Penyaringan per jenis tidak boleh kebocoran item jenis lain. */
    public function test_master_hanya_mengembalikan_item_jenis_yang_diminta(): void
    {
        $this->masterItem('Bengkel', 'Penilaian Mekanik');
        $this->masterItem('Bengkel', 'Laporan HGA');
        $this->masterItem('Cabang',  'Pemeriksaan Rekening Bank SO (Virtual & Non Virtual)');
        $this->masterItem('Cabang',  'Penitipan SMH');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=Bengkel&wilayah=RIAU')->assertOk();

        $nama = array_column($res->json('data'), 'namaPemeriksaan');
        sort($nama);
        $this->assertSame(['Laporan HGA', 'Penilaian Mekanik'], $nama);
    }

    /**
     * Jenis yang belum ada isinya di master harus mengembalikan daftar KOSONG
     * beserta keterangan jenis apa saja yang sebenarnya tersedia — bukan
     * diam-diam mengembalikan seluruh master.
     */
    public function test_jenis_tanpa_item_mengembalikan_kosong_beserta_keterangan(): void
    {
        $this->masterItem('Cabang', 'Penitipan SMH');
        $this->masterItem('Cabang', 'Pemeriksaan Rekening Bank SO (Virtual & Non Virtual)');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=Bengkel&wilayah=RIAU')->assertOk();

        $this->assertSame([], $res->json('data'), 'Item jenis lain tidak boleh ikut muncul.');
        $this->assertSame(0, $res->json('total'));
        $this->assertSame('Bengkel', $res->json('diminta.jenis'));
        $this->assertSame(['Cabang'], $res->json('tersedia.jenis'),
            'Layar harus bisa memberi tahu jenis apa yang sebenarnya ada di master.');
        $this->assertSame(2, $res->json('tersedia.total'));
    }

    /** Selama jenisnya ketemu, keterangan "tersedia" tidak perlu dikirim. */
    public function test_keterangan_tersedia_hanya_muncul_saat_kosong(): void
    {
        $this->masterItem('Bengkel', 'Penilaian Mekanik');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=Bengkel&wilayah=RIAU')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame([], $res->json('tersedia'));
    }
}
