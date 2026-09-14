<?php

namespace Tests\Feature;

use App\Models\DbHet;
use App\Models\DbMt;
use App\Models\DbPerlengkapan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Import database master ditulis massal per potongan, bukan satu baris per
 * perintah. Berkas HET nyata berisi 63.049 baris: dengan cara lama itu
 * ~126.000 query dan 2,5 menit, sehingga PHP memutusnya di batas 30 detik dan
 * seluruh transaksi dibatalkan — import gagal total tanpa satu baris pun masuk.
 */
class DatabaseImportMassalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function unggah(string $type, string $isi, string $nama = 'data.csv')
    {
        return $this->post("/api/database/{$type}/import", [
            'file' => UploadedFile::fake()->createWithContent($nama, $isi),
        ]);
    }

    // ── HET: jalur cepat (db_het punya indeks unik pada kode) ───────────────

    public function test_het_tersimpan_dengan_nilai_yang_benar(): void
    {
        $this->admin();

        $this->unggah('het', "Kodepart,Nama part,HET BARU\n"
            . "0005ZKWWA00,ENGINE ASSY,4155000\n"
            . "02510000220,LIQUID GASKET,25500\n")
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $this->assertSame(2, DbHet::count());
        $this->assertSame(4155000.0, DbHet::where('kode', '0005ZKWWA00')->value('harga_het'));
        $this->assertSame('LIQUID GASKET', DbHet::where('kode', '02510000220')->value('nama'));
    }

    public function test_unggah_ulang_menimpa_dan_tidak_menggandakan(): void
    {
        $this->admin();
        $this->unggah('het', "Kodepart,Nama part,HET BARU\n0005ZKWWA00,ENGINE ASSY,4155000\n")->assertOk();

        $this->unggah('het', "Kodepart,Nama part,HET BARU\n0005ZKWWA00,ENGINE ASSY,4900000\n")->assertOk();

        $this->assertSame(1, DbHet::count());
        $this->assertSame(4900000.0, DbHet::where('kode', '0005ZKWWA00')->value('harga_het'));
    }

    public function test_kode_kembar_dalam_satu_berkas_digabung_yang_terakhir_menang(): void
    {
        // Berkas HET nyata memang berisi 6 kodepart kembar. Satu perintah upsert
        // tidak boleh menyentuh baris yang sama dua kali, jadi kalau tidak
        // digabung lebih dulu impornya akan gagal, bukan sekadar salah angka.
        $this->admin();

        $this->unggah('het', "Kodepart,Nama part,HET BARU\n"
            . "131A2KPH881,PAKING LAMA,10000\n"
            . "131A2KPH881,PAKING BARU,17500\n")
            ->assertOk();

        $this->assertSame(1, DbHet::count());
        $this->assertSame('PAKING BARU', DbHet::where('kode', '131A2KPH881')->value('nama'));
        $this->assertSame(17500.0, DbHet::where('kode', '131A2KPH881')->value('harga_het'));
    }

    public function test_baris_tanpa_kode_tidak_menggandakan_saat_unggah_ulang(): void
    {
        // Baris berkunci null tidak bisa lewat upsert: di MySQL, NULL tidak
        // pernah sama dengan NULL, jadi indeks uniknya tidak mencegah duplikat.
        $this->admin();
        $isi = "Kodepart,Nama part,HET BARU\n,PART TANPA KODE,5000\n";

        $this->unggah('het', $isi)->assertOk();
        $this->unggah('het', $isi)->assertOk();

        $this->assertSame(1, DbHet::count());
        $this->assertNull(DbHet::first()->kode);
    }

    public function test_ditulis_massal_bukan_satu_query_per_baris(): void
    {
        // Penjaga sesungguhnya: kalau suatu saat kembali ke updateOrCreate per
        // baris, jumlah query melonjak jadi ribuan dan tes ini gagal — jauh
        // sebelum ada yang mengunggah berkas 63 ribu baris ke produksi.
        $this->admin();

        $isi = "Kodepart,Nama part,HET BARU\n";
        for ($i = 0; $i < 1000; $i++) {
            $isi .= sprintf("KODE%05d,PART %d,%d\n", $i, $i, 1000 + $i);
        }

        $jumlahQuery = 0;
        DB::listen(function () use (&$jumlahQuery) { $jumlahQuery++; });

        $this->unggah('het', $isi)->assertOk()->assertJsonPath('imported', 1000);

        $this->assertSame(1000, DbHet::count());
        $this->assertLessThan(50, $jumlahQuery,
            "1.000 baris seharusnya ditulis massal; {$jumlahQuery} query berarti kembali ke satu query per baris.");
    }

    // ── Perlengkapan: jalur lama, karena tabelnya belum punya indeks unik ───

    public function test_perlengkapan_tetap_benar_dan_tidak_menggandakan(): void
    {
        $this->admin();
        $isi = "TIPE,NOSIN,ACEH,RIAU,KEPRI\nBEAT,JM01,Helm; Toolset,Helm,\n";

        $this->unggah('perlengkapan', $isi)->assertOk();
        $this->unggah('perlengkapan', $isi)->assertOk();

        // Satu baris sumber -> dua wilayah terisi; unggah kedua menimpa, tidak menambah.
        $this->assertSame(2, DbPerlengkapan::count());
        $this->assertSame('Helm; Toolset', DbPerlengkapan::where('wilayah', 'aceh')->value('keterangan'));
    }

    // ── MT: kunci gabungan kode_peralatan + jenis ───────────────────────────

    public function test_mt_dengan_kunci_gabungan_tidak_menggandakan(): void
    {
        $this->admin();
        $isi = "No.,Nama Singkat,,Nama Peralatan (IND),Kode Peralatan,Harga\n"
             . "1,KUNCI T,,KUNCI T 10MM,07708-0010000,150000\n";

        $this->post('/api/database/mt/import', [
            'file' => UploadedFile::fake()->createWithContent('mt.csv', $isi),
            'mt_jenis' => 'umum',
        ])->assertOk();

        $this->post('/api/database/mt/import', [
            'file' => UploadedFile::fake()->createWithContent('mt.csv', $isi),
            'mt_jenis' => 'khusus',
        ])->assertOk();

        // Kode sama tapi jenis berbeda = dua baris berbeda; bukan saling menimpa.
        $this->assertSame(2, DbMt::count());
        $this->assertSame(150000.0, (float) DbMt::where('jenis', 'umum')->value('harga'));
    }
}
