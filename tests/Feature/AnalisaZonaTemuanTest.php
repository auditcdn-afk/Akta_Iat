<?php

namespace Tests\Feature;

use App\Models\AnalisaAccContract;
use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaPosisiKas;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaTemuan;
use App\Models\AnalisaUpload;
use App\Models\User;
use App\Services\AnalisaZona\Temuan\Rules\DpTipisRule;
use App\Services\AnalisaZona\Temuan\Rules\KasBelumDisetorRule;
use App\Services\AnalisaZona\Temuan\Rules\KontrakTanpaPenjualanRule;
use App\Services\AnalisaZona\Temuan\Rules\PiutangMenunggakRule;
use App\Services\AnalisaZona\Temuan\Rules\RekonKasbonRkkRule;
use App\Services\AnalisaZona\Temuan\Rules\RekonPenerimaanLpkRule;
use App\Services\AnalisaZona\Temuan\TemuanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Aturan pemeriksaan otomatis Analisa Zona. Angka-angka di test ini meniru
 * pola yang benar-benar ditemukan di data produksi SOTDB 25-27 Agustus 2026
 * (lihat komentar tiap kelas Rule), tapi nama/kode konsumennya karangan.
 *
 * Tiap aturan diuji DUA arah: menyala saat memang ada yang janggal, DAN
 * diam saat datanya wajar. Arah kedua sama pentingnya — aturan yang selalu
 * menyala akan membuat daftar temuan diabaikan auditor.
 */
class AnalisaZonaTemuanTest extends TestCase
{
    use RefreshDatabase;

    private const AWAL = '2026-08-01';
    private const AKHIR = '2026-08-31';

    private function upload(string $jenis, string $tanggal, string $hash): AnalisaUpload
    {
        return AnalisaUpload::create([
            'jenis' => $jenis, 'unit_usaha_code' => 'AAA', 'tanggal' => $tanggal,
            'source_hash' => $hash, 'source_filename' => "{$hash}.dat", 'row_count' => 1,
        ]);
    }

    private function lhpbk(string $tanggal, array $extra = []): AnalisaPosisiKas
    {
        return AnalisaPosisiKas::create(array_merge([
            'upload_id' => $this->upload('lhpbk', $tanggal, 'lh-' . $tanggal)->id,
            'unit_usaha_code' => 'AAA',
            'tanggal' => $tanggal,
            'saldo_awal_kas' => 1_000_000,
            'saldo_akhir_kas' => 1_000_000,
        ], $extra));
    }

    // ── Rekonsiliasi penerimaan unit: LPK vs LHPBK ──────────────────────────

    public function test_rekon_penerimaan_diam_saat_lpk_dan_lhpbk_cocok(): void
    {
        $this->lhpbk('2026-08-26', ['penerimaan_unit_tunai' => 127_852_000]);
        $up = $this->upload('lpk', '2026-08-26', 'lpk-1');
        AnalisaLpkPenjualan::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 104_913_000, 'kode_transaksi' => 'PBBO', 'raw_line' => '1;AAA;...']);
        AnalisaLpkPenjualan::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 22_939_000, 'kode_transaksi' => 'CRGT', 'raw_line' => '1;AAA;...']);

        $this->assertSame([], (new RekonPenerimaanLpkRule())->evaluate('AAA', self::AWAL, self::AKHIR));
    }

    public function test_rekon_penerimaan_menyala_saat_selisih(): void
    {
        $this->lhpbk('2026-08-26', ['penerimaan_unit_tunai' => 122_852_000]);
        $up = $this->upload('lpk', '2026-08-26', 'lpk-1');
        AnalisaLpkPenjualan::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 127_852_000, 'kode_transaksi' => 'PBBO', 'raw_line' => '1;AAA;...']);

        $temuan = (new RekonPenerimaanLpkRule())->evaluate('AAA', self::AWAL, self::AKHIR);

        $this->assertCount(1, $temuan);
        $this->assertSame(AnalisaTemuan::SEVERITY_TINGGI, $temuan[0]->severity);
        $this->assertSame(5_000_000.0, $temuan[0]->nominal);
        $this->assertStringContainsString('5.000.000', $temuan[0]->judul);
    }

    /**
     * CRGT (kwitansi gantung) HARUS ikut dijumlahkan — di data nyata
     * penerimaan kas LHPBK memang sama dengan total SELURUH baris LPK.
     * Menyaringnya akan membuat rekonsiliasi yang sebenarnya cocok jadi
     * terlihat selisih.
     */
    public function test_rekon_penerimaan_ikut_menghitung_kwitansi_gantung(): void
    {
        $this->lhpbk('2026-08-26', ['penerimaan_unit_tunai' => 100_000_000]);
        $up = $this->upload('lpk', '2026-08-26', 'lpk-1');
        AnalisaLpkPenjualan::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 70_000_000, 'kode_transaksi' => 'PBBO', 'raw_line' => '1;AAA;...']);
        AnalisaLpkPenjualan::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 30_000_000, 'kode_transaksi' => 'CRGT', 'raw_line' => '1;AAA;...']);

        $this->assertSame([], (new RekonPenerimaanLpkRule())->evaluate('AAA', self::AWAL, self::AKHIR));
    }

    // ── Rekonsiliasi kas kecil: RKK vs LHPBK ────────────────────────────────

    public function test_rekon_kasbon_diam_saat_cocok_dan_menyala_saat_selisih(): void
    {
        $this->lhpbk('2026-08-26', ['penggantian_kasbon' => 8_853_300, 'penggantian_kasbon_ket' => 'Via BPK No 0115 s&d 0123']);
        $up = $this->upload('rkk', '2026-08-26', 'rkk-1');
        AnalisaRkkTransaction::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 8_853_300, 'no_voucher' => '0115/AAA/VIII/2026']);

        $this->assertSame([], (new RekonKasbonRkkRule())->evaluate('AAA', self::AWAL, self::AKHIR));

        AnalisaRkkTransaction::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'nominal' => 250_000, 'no_voucher' => '0124/AAA/VIII/2026']);

        $temuan = (new RekonKasbonRkkRule())->evaluate('AAA', self::AWAL, self::AKHIR);
        $this->assertCount(1, $temuan);
        $this->assertSame(250_000.0, $temuan[0]->nominal);
        // Keterangan LHPBK ikut dibawa ke rekomendasi supaya auditor langsung
        // tahu rentang voucher mana yang harus dicocokkan.
        $this->assertStringContainsString('0115 s&d 0123', $temuan[0]->rekomendasi);
    }

    // ── Piutang menunggak ───────────────────────────────────────────────────

    /**
     * Piutang dihitung dari SNAPSHOT hari terakhir. File .ACC memuat ulang
     * seluruh piutang yang belum lunas setiap hari, jadi menggabung semua
     * hari akan menghitung piutang yang sama berkali-kali.
     */
    public function test_piutang_menunggak_hanya_dari_snapshot_hari_terakhir(): void
    {
        $lama = $this->upload('acc', '2026-08-26', 'acc-26');
        $baru = $this->upload('acc', '2026-08-27', 'acc-27');

        foreach ([[$lama, '2026-08-26'], [$baru, '2026-08-27']] as [$up, $tgl]) {
            AnalisaAccReceivable::create([
                'upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal_laporan' => $tgl,
                'tanggal_transaksi' => '2026-08-05', 'kode_konsumen' => 'TESA010190',
                'no_bukti' => 'H00011-26', 'nominal' => 19_022_000, 'raw_line' => 'F;AAA;...',
            ]);
        }

        $temuan = (new PiutangMenunggakRule())->evaluate('AAA', self::AWAL, self::AKHIR);

        $this->assertCount(1, $temuan);
        // Kalau kedua hari ikut dijumlah, nominalnya jadi 38.044.000.
        $this->assertSame(19_022_000.0, $temuan[0]->nominal);
        $this->assertCount(1, $temuan[0]->detail['items']);
        $this->assertSame(22, $temuan[0]->detail['items'][0]['umur_hari']);
    }

    public function test_piutang_masih_muda_atau_bernilai_kecil_tidak_dilaporkan(): void
    {
        $up = $this->upload('acc', '2026-08-27', 'acc-27');
        // Baru 3 hari — belum lewat ambang umur.
        AnalisaAccReceivable::create([
            'upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal_laporan' => '2026-08-27',
            'tanggal_transaksi' => '2026-08-24', 'kode_konsumen' => 'MUDA010190',
            'no_bukti' => 'H00145-26', 'nominal' => 17_761_000, 'raw_line' => 'F;AAA;...',
        ]);
        // Sudah tua tapi nominalnya di bawah ambang — tidak sepadan untuk dikejar.
        AnalisaAccReceivable::create([
            'upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal_laporan' => '2026-08-27',
            'tanggal_transaksi' => '2026-08-01', 'kode_konsumen' => 'KECI010190',
            'no_bukti' => 'H00001-26', 'nominal' => 250_000, 'raw_line' => 'F;AAA;...',
        ]);

        $this->assertSame([], (new PiutangMenunggakRule())->evaluate('AAA', self::AWAL, self::AKHIR));
    }

    // ── Kontrak tanpa penjualan ─────────────────────────────────────────────

    public function test_kontrak_tanpa_baris_lpk_dilaporkan_yang_berpasangan_tidak(): void
    {
        $upAcc = $this->upload('acc', '2026-08-26', 'acc-26');
        AnalisaAccContract::create(['upload_id' => $upAcc->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'no_bukti' => 'H00156-26', 'kode_konsumen' => 'YATI010190', 'harga_otr' => 25_200_000, 'dp' => 5_000_000, 'raw_line' => '1;AAA;...']);
        AnalisaAccContract::create(['upload_id' => $upAcc->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'no_bukti' => 'H00154-26', 'kode_konsumen' => 'PUNY010190', 'harga_otr' => 23_700_000, 'dp' => 5_000_000, 'raw_line' => '1;AAA;...']);

        // Hanya H00154-26 yang punya pasangan di LPK — dan sengaja di TANGGAL
        // BERBEDA, karena penjualan boleh dilaporkan di hari lain dan itu
        // bukan kejanggalan.
        $upLpk = $this->upload('lpk', '2026-08-27', 'lpk-27');
        AnalisaLpkPenjualan::create(['upload_id' => $upLpk->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-27', 'no_bukti' => 'H00154-26', 'nominal' => 3_000_000, 'kode_transaksi' => 'PBBO', 'raw_line' => '1;AAA;...']);

        $temuan = (new KontrakTanpaPenjualanRule())->evaluate('AAA', self::AWAL, self::AKHIR);

        $this->assertCount(1, $temuan);
        $this->assertCount(1, $temuan[0]->detail['items']);
        $this->assertSame('H00156-26', $temuan[0]->detail['items'][0]['no_bukti']);
    }

    // ── Kas belum disetor ───────────────────────────────────────────────────

    public function test_kas_menginap_di_atas_ambang_dilaporkan(): void
    {
        $this->lhpbk('2026-08-25', ['saldo_akhir_kas' => 3_980_380]);   // wajar
        $this->lhpbk('2026-08-26', ['saldo_akhir_kas' => 75_000_000]);  // lewat ambang

        $temuan = (new KasBelumDisetorRule())->evaluate('AAA', self::AWAL, self::AKHIR);

        $this->assertCount(1, $temuan);
        $this->assertSame(75_000_000.0, $temuan[0]->nominal);
        $this->assertCount(1, $temuan[0]->detail['items']);
    }

    // ── DP tipis ────────────────────────────────────────────────────────────

    public function test_dp_tipis_dilaporkan_dan_kontrak_tanpa_otr_dilewati(): void
    {
        $up = $this->upload('acc', '2026-08-26', 'acc-26');
        AnalisaAccContract::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'no_bukti' => 'H00160-26', 'kode_konsumen' => 'TIPI010190', 'harga_otr' => 33_461_000, 'dp' => 4_627_000, 'raw_line' => '1;AAA;...']); // 13,8%
        AnalisaAccContract::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'no_bukti' => 'H00161-26', 'kode_konsumen' => 'TEBA010190', 'harga_otr' => 21_329_000, 'dp' => 3_877_000, 'raw_line' => '1;AAA;...']); // 18,2%
        // Tanpa harga OTR: datanya tidak lengkap untuk dinilai, bukan DP nol.
        AnalisaAccContract::create(['upload_id' => $up->id, 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-26', 'no_bukti' => 'H00162-26', 'kode_konsumen' => 'KOSO010190', 'harga_otr' => 0, 'dp' => 0, 'raw_line' => '1;AAA;...']);

        $temuan = (new DpTipisRule())->evaluate('AAA', self::AWAL, self::AKHIR);

        $this->assertCount(1, $temuan);
        $this->assertCount(1, $temuan[0]->detail['items']);
        $this->assertSame('H00160-26', $temuan[0]->detail['items'][0]['no_bukti']);
    }

    // ── Service + endpoint ──────────────────────────────────────────────────

    public function test_rebuild_menghapus_temuan_lama_supaya_tidak_basi(): void
    {
        AnalisaTemuan::create([
            'unit_usaha_code' => 'AAA', 'periode' => '2026-08', 'kode_rule' => 'lama',
            'judul' => 'Temuan lama yang sudah tidak berlaku', 'severity' => 'tinggi',
            'rekomendasi' => '-',
        ]);

        app(TemuanService::class)->rebuild('AAA', '2026-08', self::AWAL, self::AKHIR);

        // Tidak ada data sama sekali -> tidak ada temuan baru, DAN temuan lama
        // harus ikut hilang (bukan menumpuk).
        $this->assertSame(0, AnalisaTemuan::where('unit_usaha_code', 'AAA')->count());
    }

    public function test_endpoint_temuan_urut_dari_severity_tertinggi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'analisa_zona_access' => true]));

        foreach ([['rendah', 'Kecil'], ['tinggi', 'Genting'], ['sedang', 'Menengah']] as [$sev, $judul]) {
            AnalisaTemuan::create([
                'unit_usaha_code' => 'AAA', 'periode' => '2026-08', 'kode_rule' => 'uji',
                'judul' => $judul, 'severity' => $sev, 'rekomendasi' => 'periksa',
            ]);
        }

        $res = $this->getJson('/api/analisa-zona/temuan?periode=2026-08')->assertOk();

        $this->assertSame(['Genting', 'Menengah', 'Kecil'], collect($res->json('data'))->pluck('judul')->all());
        $this->assertSame(3, $res->json('meta.total'));
    }

    public function test_endpoint_temuan_ditolak_untuk_user_tanpa_akses(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'analisa_zona_access' => false]));

        $this->getJson('/api/analisa-zona/temuan')->assertForbidden();
    }
}
