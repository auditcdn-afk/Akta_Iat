<?php

namespace Tests\Feature;

use App\Models\DbAhmOil;
use App\Models\DbGrading;
use App\Models\DbHet;
use App\Models\DbMt;
use App\Models\DbPerlengkapan;
use App\Models\DbPlafon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tambah/Edit SATU baris dari layar Database.
 *
 * Jalur ini sempat tidak bisa dipakai sama sekali pada Database HET: db_het.kode
 * unik dan masternya berisi puluhan ribu baris, sementara store() tidak memeriksa
 * apa pun dan tidak menangkap penolakan database — sehingga kode yang sudah ada
 * berujung 500 "Server Error", dan pesannya pun tertutup selubung modal.
 *
 * Di samping itu store() memakai $colMap (urutan kolom berkas IMPORT) sebagai
 * daftar kolom yang boleh ditulis, jadi isian yang tidak kebetulan ada di urutan
 * itu dibuang tanpa pemberitahuan: Satuan & Keterangan pada HET, Harga & Jenis
 * pada MT.
 */
class DatabaseTambahSatuItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** @param array<string, mixed> $data */
    private function tambah(string $type, array $data)
    {
        return $this->postJson("/api/database/{$type}", $data);
    }

    /** Isian persis seperti yang dikirim form Database HET. */
    private function isianHet(array $ganti = []): array
    {
        return array_merge([
            'kode'       => '86100H05PA0',
            'nama'       => 'HELMET ASSY RETRO STYLO',
            'harga_het'  => '334000',
            'satuan'     => 'PCS',
            'keterangan' => 'Helm bawaan unit',
        ], $ganti);
    }

    // ── HET: bug yang dilaporkan ────────────────────────────────────────────

    public function test_het_kode_yang_sudah_ada_ditolak_dengan_pesan_yang_bisa_dibaca(): void
    {
        $this->admin();
        DbHet::create(['kode' => '86100H05PA0', 'nama' => 'HELMET LAMA', 'harga_het' => 300000]);

        $res = $this->tambah('het', $this->isianHet())->assertStatus(422);

        $pesan = $res->json('message');
        $this->assertStringContainsString('86100H05PA0', $pesan);
        $this->assertStringContainsString('Database HET', $pesan);
        $this->assertStringContainsString('HELMET LAMA', $pesan, 'pesannya harus menyebut baris mana yang memakai kode itu');
        $this->assertStringContainsString('Edit', $pesan, 'pengguna perlu diberi tahu apa yang harus dilakukan');

        $this->assertSame(1, DbHet::count());
        $this->assertSame('HELMET LAMA', DbHet::first()->nama, 'baris lama tidak boleh tertimpa');
    }

    public function test_het_baris_yang_sudah_sama_persis_diberi_tahu_apa_adanya(): void
    {
        $this->admin();

        // Kejadian nyata: 86100H05PA0 "HELMET ASSY RETRO STYLO" Rp334.000 sudah
        // ada di master HET, dan yang diketik ulang isinya sama semua.
        DbHet::create($this->isianHet(['harga_het' => 334000]));

        $pesan = $this->tambah('het', $this->isianHet())->assertStatus(422)->json('message');

        $this->assertStringContainsString('sudah sama persis', $pesan);
        $this->assertStringNotContainsString('klik Edit', $pesan, 'tidak ada yang perlu diedit kalau isinya sama');
        $this->assertSame(1, DbHet::count());
    }

    public function test_het_kode_huruf_kecil_dianggap_sama_dengan_yang_sudah_ada(): void
    {
        $this->admin();
        DbHet::create(['kode' => '86100H05PA0', 'nama' => 'HELMET LAMA', 'harga_het' => 300000]);

        // Yang diketik pengguna: 86100h05pa0. Sebelum diperbaiki ini lolos dan
        // membuat baris kedua yang tidak akan pernah ketemu di layar pemeriksaan.
        $this->tambah('het', $this->isianHet(['kode' => '86100h05pa0']))->assertStatus(422);
        $this->assertSame(1, DbHet::count());
    }

    public function test_het_kode_disimpan_huruf_besar(): void
    {
        $this->admin();

        $this->tambah('het', $this->isianHet(['kode' => ' 86100h05pa0 ']))->assertCreated();

        $this->assertSame('86100H05PA0', DbHet::first()->kode);
    }

    public function test_het_satuan_dan_keterangan_ikut_tersimpan(): void
    {
        $this->admin();

        $this->tambah('het', $this->isianHet())
            ->assertCreated()
            ->assertJsonPath('data.satuan', 'PCS')
            ->assertJsonPath('data.keterangan', 'Helm bawaan unit');

        $baris = DbHet::first();
        $this->assertSame('PCS', $baris->satuan);
        $this->assertSame('Helm bawaan unit', $baris->keterangan);
        $this->assertSame(334000.0, $baris->harga_het);
    }

    public function test_het_tanpa_nama_atau_harga_ditolak_422_bukan_500(): void
    {
        $this->admin();

        $this->tambah('het', $this->isianHet(['nama' => '']))->assertStatus(422);
        $this->tambah('het', $this->isianHet(['harga_het' => '']))->assertStatus(422);
        $this->tambah('het', $this->isianHet(['harga_het' => 'abc']))->assertStatus(422);

        $this->assertSame(0, DbHet::count());
    }

    public function test_het_dua_baris_tanpa_kode_tidak_bentrok(): void
    {
        $this->admin();

        // Kode kosong disimpan NULL, bukan "". Indeks unik memperbolehkan NULL
        // berulang; "" berulang akan ditolak database.
        $this->tambah('het', $this->isianHet(['kode' => '', 'nama' => 'TANPA KODE A']))->assertCreated();
        $this->tambah('het', $this->isianHet(['kode' => '', 'nama' => 'TANPA KODE B']))->assertCreated();

        $this->assertSame(2, DbHet::count());
        $this->assertSame(2, DbHet::whereNull('kode')->count());
    }

    // ── MT: isian yang ikut hilang ──────────────────────────────────────────

    public function test_mt_harga_dan_jenis_ikut_tersimpan(): void
    {
        $this->admin();

        $this->tambah('mt', [
            'nomor' => '1', 'nama_singkat' => 'KUNCI T', 'nama_peralatan' => 'KUNCI T 10MM',
            'kode_peralatan' => 'AHM-001', 'harga' => '150000', 'jenis' => 'MT FI',
        ])->assertCreated()
            ->assertJsonPath('data.harga', 150000)
            ->assertJsonPath('data.jenis', 'MT FI');

        $baris = DbMt::first();
        $this->assertSame(150000.0, $baris->harga);
        $this->assertSame('MT FI', $baris->jenis);
    }

    public function test_mt_kode_peralatan_sama_beda_jenis_tetap_boleh(): void
    {
        $this->admin();

        // Kunci uniknya kode_peralatan + jenis, bukan kode_peralatan saja.
        $this->tambah('mt', ['kode_peralatan' => 'AHM-001', 'nama_peralatan' => 'KUNCI T', 'jenis' => 'MT FI'])->assertCreated();
        $this->tambah('mt', ['kode_peralatan' => 'AHM-001', 'nama_peralatan' => 'KUNCI T', 'jenis' => 'MT Lama'])->assertCreated();
        $this->tambah('mt', ['kode_peralatan' => 'AHM-001', 'nama_peralatan' => 'KUNCI T LAIN', 'jenis' => 'MT FI'])->assertStatus(422);

        $this->assertSame(2, DbMt::count());
    }

    // ── Tipe lain ───────────────────────────────────────────────────────────

    public function test_ahm_oil_kode_disimpan_huruf_besar_dan_duplikatnya_ditolak(): void
    {
        $this->admin();

        $this->tambah('ahm-oil', ['kode' => '08232m99k1lb0', 'nama' => 'MPX2 0.8L'])->assertCreated();
        $this->assertSame('08232M99K1LB0', DbAhmOil::first()->kode);

        $this->tambah('ahm-oil', ['kode' => '08232M99K1LB0', 'nama' => 'MPX2 LAIN'])->assertStatus(422);
        $this->assertSame(1, DbAhmOil::count());
    }

    public function test_plafon_duplikat_kode_ditolak_dan_baris_lama_utuh(): void
    {
        $this->admin();

        $this->tambah('plafon', ['kode' => 'P01', 'nama' => 'KAS KECIL', 'nilai' => '5000000'])->assertCreated();
        $this->tambah('plafon', ['kode' => 'P01', 'nama' => 'LAIN', 'nilai' => '9000000'])->assertStatus(422);

        $this->assertSame(1, DbPlafon::count());
        $this->assertSame('KAS KECIL', DbPlafon::first()->nama);
    }

    public function test_perlengkapan_kode_huruf_besar_dan_tetap_boleh_berulang(): void
    {
        $this->admin();

        // db_perlengkapan TIDAK punya indeks unik, jadi jangan ada penolakan baru
        // di sini — satu kode memang punya beberapa baris (satu per wilayah).
        $this->tambah('perlengkapan', ['kode' => 'jf51e', 'wilayah' => 'aceh', 'nama' => 'VARIO 125', 'keterangan' => 'Spion, Helm'])->assertCreated();
        $this->tambah('perlengkapan', ['kode' => 'JF51E', 'wilayah' => 'riau', 'nama' => 'VARIO 125', 'keterangan' => 'Spion'])->assertCreated();

        $this->assertSame(2, DbPerlengkapan::count());
        $this->assertSame(['JF51E', 'JF51E'], DbPerlengkapan::orderBy('id')->pluck('kode')->all());
    }

    public function test_grading_id_sama_beda_wilayah_tetap_boleh(): void
    {
        $this->admin();

        $this->tambah('grading', ['id_grading' => 'G1151', 'wilayah' => 'Aceh', 'nama_pemeriksaan' => 'Kas'])->assertCreated();
        $this->tambah('grading', ['id_grading' => 'G1151', 'wilayah' => 'Riau', 'nama_pemeriksaan' => 'Kas'])->assertCreated();
        $this->tambah('grading', ['id_grading' => 'G1151', 'wilayah' => 'Aceh', 'nama_pemeriksaan' => 'Kas lain'])->assertStatus(422);

        $this->assertSame(2, DbGrading::count());
    }

    // ── Edit satu baris ─────────────────────────────────────────────────────

    public function test_edit_baris_dengan_kode_sendiri_tidak_dianggap_bentrok(): void
    {
        $this->admin();
        $baris = DbHet::create(['kode' => '86100H05PA0', 'nama' => 'HELMET LAMA', 'harga_het' => 300000]);

        $this->putJson("/api/database/het/{$baris->id}", $this->isianHet(['nama' => 'HELMET BARU']))
            ->assertOk();

        $baris->refresh();
        $this->assertSame('HELMET BARU', $baris->nama);
        $this->assertSame('PCS', $baris->satuan, 'Satuan ikut tersimpan saat edit juga');
        $this->assertSame(334000.0, $baris->harga_het);
    }

    public function test_edit_ke_kode_milik_baris_lain_ditolak_422(): void
    {
        $this->admin();
        DbHet::create(['kode' => '86100H05PA0', 'nama' => 'HELMET A', 'harga_het' => 300000]);
        $b = DbHet::create(['kode' => '86100H05PB0', 'nama' => 'HELMET B', 'harga_het' => 310000]);

        $this->putJson("/api/database/het/{$b->id}", $this->isianHet(['nama' => 'HELMET B']))
            ->assertStatus(422);

        $this->assertSame('86100H05PB0', $b->fresh()->kode, 'kode baris B tidak boleh berubah');
    }

    // ── Hak akses tidak berubah ─────────────────────────────────────────────

    public function test_bukan_admin_tetap_tidak_boleh_menambah(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->tambah('het', $this->isianHet())->assertForbidden();
        $this->assertSame(0, DbHet::count());
    }
}
