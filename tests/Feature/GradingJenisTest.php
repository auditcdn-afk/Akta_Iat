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

    /**
     * Jenis grading harus ketahuan sendiri dari jenis audit plan-nya, DAN
     * dikembalikan dalam sebutan yang dipakai master — di master milik auditor
     * sebutannya "CSC" dan "SO", bukan "Bengkel" dan "Cabang".
     */
    #[DataProvider('petaJenis')]
    public function test_jenis_grading_ditebak_dari_jenis_audit(string $jenisAudit, string $cabang, ?string $harap): void
    {
        // Master memakai sebutan CSC / SO / WHS PART / WHS UNIT.
        foreach (['CSC', 'SO', 'WHS PART', 'WHS UNIT'] as $j) $this->masterItem($j, 'Contoh ' . $j);

        $plan = $this->plan($jenisAudit, $cabang);

        $res = $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->assertOk();

        $this->assertSame($harap, $res->json('data.jenisGrading'),
            "\"{$jenisAudit}\" di {$cabang} seharusnya jenis grading " . ($harap ?? 'kosong'));
    }

    public static function petaJenis(): array
    {
        return [
            'Audit Full CSC -> CSC'          => ['Audit Full CSC', 'CSC UJT', 'CSC'],
            'Audit Full SO -> SO'            => ['Audit Full SO', 'SO UJT', 'SO'],
            'Warehouse PART -> WHS PART'     => ['Audit Warehouse PART', 'WHS Part KIM', 'WHS PART'],
            'Warehouse UNIT -> WHS UNIT'     => ['Audit Warehouse UNIT', 'WHS Unit KIM', 'WHS UNIT'],
            'Serah Terima Workshop -> CSC'   => ['Audit Serah Terima Workshop Head', 'CSC PRW', 'CSC'],
            'Serah Terima SO Head -> SO'     => ['Audit Serah Terima Sales Office Head', 'SO PRW', 'SO'],
            // Jenis audit yang netral: yang menentukan nama unit usahanya.
            'Kas + BPKB di CSC -> CSC'       => ['Audit Kas + BPKB', 'CSC UJT', 'CSC'],
            'Kas + BPKB di SO -> SO'         => ['Audit Kas + BPKB', 'SO UJT', 'SO'],
            // Benar-benar tidak bisa ditebak: dibiarkan kosong, auditor memilih sendiri.
            'Verifikasi HO -> kosong'        => ['Audit Verifikasi HO', 'HO MEDAN', null],
        ];
    }

    /**
     * Inti persoalannya: sebutan jenis berbeda-beda antar tempat. Master boleh
     * menulis "Bengkel" atau "CSC" -- jawabannya harus mengikuti master, bukan
     * memaksakan satu sebutan.
     */
    public function test_jenis_mengikuti_sebutan_yang_dipakai_master(): void
    {
        $this->masterItem('Bengkel', 'Penilaian Mekanik');
        $this->masterItem('Cabang',  'Penitipan SMH');
        $plan = $this->plan('Audit Full CSC', 'CSC UJT');

        $this->assertSame('Bengkel',
            $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->json('data.jenisGrading'));
    }

    /** Master masih kosong: dipakai sebutan bakunya supaya tombolnya tetap terpilih. */
    public function test_master_kosong_memakai_sebutan_baku(): void
    {
        $plan = $this->plan('Audit Full CSC', 'CSC UJT');

        $this->assertSame('CSC',
            $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->json('data.jenisGrading'));
    }

    /** Daftar jenis untuk tombol diambil dari master, bukan daftar tetap. */
    public function test_daftar_jenis_untuk_tombol_diambil_dari_master(): void
    {
        $this->masterItem('CSC', 'Pemeriksaan Kas Kecil');
        $this->masterItem('SO', 'Penitipan SMH');
        $this->masterItem('WHS PART', 'Stock Opname Part');

        $res = $this->getJson('/api/audit-detail/grading/jenis')->assertOk();

        $this->assertSame(['CSC', 'SO', 'WHS PART'], $res->json('data'));
    }

    /** Wilayah tetap diambil dari master unit usaha seperti sebelumnya. */
    public function test_wilayah_diambil_dari_master_unit_usaha(): void
    {
        DbUnitUsaha::query()->create(['unit_usaha' => 'CSC UJT', 'wilayah' => 'RIAU', 'jenis' => 'Cabang']);
        $plan = $this->plan('Audit Full CSC', 'CSC UJT');

        $res = $this->getJson("/api/audit-detail/grading/plan-info?plan_audit_id={$plan->id}")->assertOk();

        $this->assertSame('RIAU', $res->json('data.area'));
        $this->assertSame('CSC', $res->json('data.jenisGrading'),
            'Master unit usaha mencatat CSC sebagai "Cabang"; jenis audit harus lebih menentukan.');
    }

    /** Penyaringan per jenis tidak boleh kebocoran item jenis lain. */
    public function test_master_hanya_mengembalikan_item_jenis_yang_diminta(): void
    {
        $this->masterItem('CSC', 'Penilaian Mekanik');
        $this->masterItem('CSC', 'Laporan HGA');
        $this->masterItem('SO',  'Pemeriksaan Rekening Bank SO (Virtual & Non Virtual)');
        $this->masterItem('SO',  'Penitipan SMH');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=CSC&wilayah=RIAU')->assertOk();

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
        $this->masterItem('SO', 'Penitipan SMH');
        $this->masterItem('SO', 'Pemeriksaan Rekening Bank SO (Virtual & Non Virtual)');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=CSC&wilayah=RIAU')->assertOk();

        $this->assertSame([], $res->json('data'), 'Item jenis lain tidak boleh ikut muncul.');
        $this->assertSame(0, $res->json('total'));
        $this->assertSame('CSC', $res->json('diminta.jenis'));
        $this->assertSame(['SO'], $res->json('tersedia.jenis'),
            'Layar harus bisa memberi tahu jenis apa yang sebenarnya ada di master.');
        $this->assertSame(2, $res->json('tersedia.total'));
    }

    /** Selama jenisnya ketemu, keterangan "tersedia" tidak perlu dikirim. */
    public function test_keterangan_tersedia_hanya_muncul_saat_kosong(): void
    {
        $this->masterItem('CSC', 'Penilaian Mekanik');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=CSC&wilayah=RIAU')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame([], $res->json('tersedia'));
    }

    /**
     * Master grading menulis wilayah "Aceh"/"Riau", sementara master unit usaha
     * menulisnya "ACEH"/"RIAU". Beda besar-kecil huruf tidak boleh membuat
     * penyaringannya meleset.
     */
    public function test_wilayah_dicocokkan_tanpa_memandang_besar_kecil_huruf(): void
    {
        $this->masterItem('CSC', 'Pemeriksaan Kas Kecil', 'Riau');

        $res = $this->getJson('/api/audit-detail/grading/master?jenis=csc&wilayah=RIAU')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Pemeriksaan Kas Kecil', $res->json('data.0.namaPemeriksaan'));
    }

    /**
     * Grading yang disimpan sebelum sebutan jenisnya diseragamkan menyimpan
     * "Bengkel"/"Cabang", sementara master menulisnya "CSC"/"SO". Dibuka apa
     * adanya, jenis itu tidak punya isi di master: daftar itemnya kosong dan
     * tombol bersebutan lama ikut nongol seolah-olah pilihan yang sah.
     */
    public function test_jenis_tersimpan_yang_sebutannya_lama_diterjemahkan_ke_sebutan_master(): void
    {
        $this->masterItem('CSC', 'Penilaian Mekanik');
        $this->masterItem('SO',  'Penitipan SMH');

        $plan = $this->plan('Audit Full CSC', 'CSC UJT');
        \App\Models\AuditGrading::query()->create([
            'plan_audit_id' => $plan->id,
            'jenis'         => 'Bengkel',   // sebutan lama
            'area'          => 'RIAU',
            'details'       => [],
        ]);

        $res = $this->getJson("/api/audit-detail/grading?plan_audit_id={$plan->id}")->assertOk();

        $this->assertSame('CSC', $res->json('data.jenis'),
            '"Bengkel" dan "CSC" satu keluarga; yang dipakai harus sebutan master.');
    }

    /** Jenis tersimpan yang memang sudah sesuai master tidak diutak-atik. */
    public function test_jenis_tersimpan_yang_sudah_sesuai_dibiarkan(): void
    {
        $this->masterItem('CSC', 'Penilaian Mekanik');

        $plan = $this->plan('Audit Full CSC', 'CSC UJT');
        \App\Models\AuditGrading::query()->create([
            'plan_audit_id' => $plan->id, 'jenis' => 'CSC', 'area' => 'RIAU', 'details' => [],
        ]);

        $this->assertSame('CSC',
            $this->getJson("/api/audit-detail/grading?plan_audit_id={$plan->id}")->json('data.jenis'));
    }

    /** Yang tidak dikenali sama sekali dibiarkan apa adanya, bukan digeser diam-diam. */
    public function test_jenis_tersimpan_yang_asing_tidak_digeser(): void
    {
        $this->masterItem('CSC', 'Penilaian Mekanik');

        $plan = $this->plan('Audit Full CSC', 'CSC UJT');
        \App\Models\AuditGrading::query()->create([
            'plan_audit_id' => $plan->id, 'jenis' => 'Lain-Lain', 'area' => 'RIAU', 'details' => [],
        ]);

        $this->assertSame('Lain-Lain',
            $this->getJson("/api/audit-detail/grading?plan_audit_id={$plan->id}")->json('data.jenis'));
    }

    /**
     * Penjaga inti: daftar item untuk satu jenis tidak boleh memuat satu pun
     * item milik jenis lain, sebanyak apa pun isi masternya.
     */
    public function test_daftar_item_csc_tidak_memuat_item_milik_so(): void
    {
        foreach (['Area Gedung', 'Pemeriksaan Kas Kecil', 'Penilaian Mekanik', 'Selisih Kas Besar/Kecil'] as $n) {
            $this->masterItem('CSC', $n, 'Aceh');
        }
        foreach (['Pemeriksaan Rekening Bank SO (Virtual & Non Virtual)', 'Penitipan SMH',
                  'Cash Gantung', 'Setoran ke H1'] as $n) {
            $this->masterItem('SO', $n, 'Aceh');
        }

        // Wilayah plan (RIAU) tidak ada di master -> jatuh ke "semua wilayah",
        // dan justru di situlah dulu item jenis lain ikut terbawa.
        $res = $this->getJson('/api/audit-detail/grading/master?jenis=CSC')->assertOk();

        $nama = array_column($res->json('data'), 'namaPemeriksaan');
        sort($nama);
        $this->assertSame(
            ['Area Gedung', 'Pemeriksaan Kas Kecil', 'Penilaian Mekanik', 'Selisih Kas Besar/Kecil'],
            $nama);
    }
}
