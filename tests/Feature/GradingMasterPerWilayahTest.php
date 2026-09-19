<?php

namespace Tests\Feature;

use App\Models\DbGrading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Master grading unik per (ID Grading, Wilayah), bukan per ID Grading saja.
 *
 * Berkas master yang dipakai auditor memakai ULANG ID yang sama untuk wilayah
 * berbeda: G1151 ada untuk Aceh, Riau, dan Kepri sekaligus. Dikunci pada
 * id_grading saja, impor menimpa baris yang sudah masuk dan hanya wilayah
 * terakhir di berkas yang tersisa.
 *
 * Pada berkas nyata 960 baris, 200 di antaranya hilang tanpa pesan apa pun:
 * WHS PART 165 -> 55 dan WHS UNIT 135 -> 45, sehingga audit gudang di Aceh dan
 * Riau kehilangan seluruh item grading-nya.
 */
class GradingMasterPerWilayahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** Berkas import: header sama persis dengan berkas master auditor. */
    private function berkas(array $baris): UploadedFile
    {
        $isi = "IDGrading,Jenis,Area,Nama Pemeriksaan,Hasil Pemeriksaan,Nilai\n";
        foreach ($baris as $b) $isi .= implode(',', $b) . "\n";

        return UploadedFile::fake()->createWithContent('Database_Grading.csv', $isi);
    }

    private function unggah(UploadedFile $file)
    {
        // Header Accept sama dengan yang dikirim halaman Database (authHeaders
        // di akta-database.js) — supaya pesan penolakannya sampai ke layar
        // sebagai JSON, bukan halaman error.
        return $this->post('/api/database/grading/import', ['file' => $file],
            ['Accept' => 'application/json']);
    }

    /** ID yang sama di tiga wilayah harus masuk SEMUA, bukan saling menimpa. */
    public function test_id_grading_yang_sama_di_wilayah_berbeda_tidak_saling_menimpa(): void
    {
        $this->unggah($this->berkas([
            ['G1151', 'WHS UNIT', 'Aceh',  'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Riau',  'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Kepri', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
        ]))->assertOk();

        $this->assertSame(3, DbGrading::where('id_grading', 'G1151')->count(),
            'Satu ID dipakai tiga wilayah — ketiganya harus tersimpan.');
        $this->assertSame(['Aceh', 'Kepri', 'Riau'],
            DbGrading::where('id_grading', 'G1151')->orderBy('wilayah')->pluck('wilayah')->all());
    }

    /**
     * Bentuk aslinya: gudang punya item yang sama untuk tiga wilayah dengan ID
     * berulang, sementara SO/CSC ID-nya tidak berulang. Tidak satu baris pun
     * boleh hilang.
     */
    public function test_seluruh_baris_berkas_masuk_tanpa_ada_yang_hilang(): void
    {
        $baris = [];
        foreach (['Aceh', 'Riau', 'Kepri'] as $w) {
            foreach (range(1, 5) as $n) {
                $baris[] = ['G11' . str_pad((string) (50 + $n), 2, '0'), 'WHS UNIT', $w,
                            'Pemeriksaan Kas Kecil', $n . '. hasil ke-' . $n, $n];
            }
        }
        // SO: ID tidak berulang antar wilayah
        $no = 1;
        foreach (['Aceh', 'Riau', 'Kepri'] as $w) {
            foreach (range(1, 5) as $n) {
                $baris[] = ['G0' . str_pad((string) $no++, 3, '0'), 'SO', $w,
                            'Penitipan SMH', $n . '. hasil ke-' . $n, $n];
            }
        }

        $this->unggah($this->berkas($baris))->assertOk();

        $this->assertSame(30, DbGrading::count(), 'Berkas 30 baris harus masuk 30 baris.');
        $this->assertSame(15, DbGrading::where('jenis', 'WHS UNIT')->count());
        $this->assertSame(5,  DbGrading::where('jenis', 'WHS UNIT')->where('wilayah', 'Aceh')->count());
        $this->assertSame(5,  DbGrading::where('jenis', 'WHS UNIT')->where('wilayah', 'Riau')->count());
        $this->assertSame(5,  DbGrading::where('jenis', 'WHS UNIT')->where('wilayah', 'Kepri')->count());
    }

    /** Unggah ulang berkas yang sama tetap menimpa, bukan menggandakan. */
    public function test_unggah_ulang_tidak_menggandakan(): void
    {
        $baris = [
            ['G1151', 'WHS UNIT', 'Aceh', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Riau', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
        ];

        $this->unggah($this->berkas($baris))->assertOk();
        $this->unggah($this->berkas($baris))->assertOk();

        $this->assertSame(2, DbGrading::count());
    }

    /** Perubahan nilai pada unggahan berikutnya tetap tersimpan per wilayah. */
    public function test_perubahan_nilai_tersimpan_per_wilayah(): void
    {
        $this->unggah($this->berkas([
            ['G1151', 'WHS UNIT', 'Aceh', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Riau', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
        ]))->assertOk();

        $this->unggah($this->berkas([
            ['G1151', 'WHS UNIT', 'Aceh', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Riau', 'Pemeriksaan Kas Kecil', '5. sesuai', 4],
        ]))->assertOk();

        $this->assertSame(2, DbGrading::count());
        $this->assertEqualsWithDelta(1.0, (float) DbGrading::where('wilayah', 'Aceh')->value('nilai'), 0.001);
        $this->assertEqualsWithDelta(4.0, (float) DbGrading::where('wilayah', 'Riau')->value('nilai'), 0.001);
    }

    /**
     * Kalau struktur tabel di hosting masih yang lama (indeks unik pada
     * id_grading saja) sementara berkasnya memakai ID berulang, import dulu
     * merayap satu-satu selama puluhan detik lalu kandas dengan "Server Error"
     * -- tanpa petunjuk bahwa yang kurang cuma pembaruan struktur.
     */
    public function test_struktur_lama_ditolak_di_depan_dengan_pesan_yang_jelas(): void
    {
        // Kembalikan ke keadaan sebelum migration dijalankan.
        Schema::table('db_grading', fn(Blueprint $t) => $t->dropUnique('db_grading_id_grading_wilayah_unique'));
        Schema::table('db_grading', fn(Blueprint $t) => $t->unique('id_grading', 'db_grading_id_grading_unique'));

        $res = $this->unggah($this->berkas([
            ['G1151', 'WHS UNIT', 'Aceh', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
            ['G1151', 'WHS UNIT', 'Riau', 'Pemeriksaan Kas Kecil', '5. sesuai', 1],
        ]))->assertStatus(422);

        $pesan = (string) $res->json('message');
        $this->assertStringContainsString('Struktur database', $pesan);
        $this->assertStringContainsString('id_grading', $pesan);
        $this->assertStringContainsString('/deploy/migrate', $pesan);

        $this->assertSame(0, DbGrading::count(),
            'Ditolak di depan: tidak boleh ada baris yang sempat tertulis.');
    }

    /** Struktur lama yang kebetulan masih cocok dengan isi berkas tidak diganggu. */
    public function test_struktur_lama_tetap_dilayani_kalau_berkasnya_tidak_memakai_id_berulang(): void
    {
        Schema::table('db_grading', fn(Blueprint $t) => $t->dropUnique('db_grading_id_grading_wilayah_unique'));
        Schema::table('db_grading', fn(Blueprint $t) => $t->unique('id_grading', 'db_grading_id_grading_unique'));

        $this->unggah($this->berkas([
            ['G0001', 'SO', 'Aceh', 'Penitipan SMH', '5. sesuai', 1],
            ['G0002', 'SO', 'Riau', 'Penitipan SMH', '5. sesuai', 1],
        ]))->assertOk();

        $this->assertSame(2, DbGrading::count());
    }
}
