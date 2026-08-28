<?php

namespace Tests\Unit;

use App\Services\AnalisaZona\Parsers\AccParser;
use App\Services\AnalisaZona\Parsers\LpkParser;
use App\Services\AnalisaZona\Parsers\ParserRegistry;
use App\Services\AnalisaZona\Parsers\RkkParser;
use Tests\TestCase;

/**
 * Data di bawah ini SINTETIS (nama/NIK/no HP karangan) tapi meniru persis
 * struktur baris file .RKK/.ACC/.LPK asli dari sistem unit usaha — supaya
 * tidak ada data pribadi konsumen nyata yang ikut tersimpan di repo git.
 */
class AnalisaZonaParserTest extends TestCase
{
    public function test_rkk_parser_mengambil_baris_detail_dengan_konteks_header(): void
    {
        $content = implode("\r\n", [
            '19f4c963a56b21c376cf7ba23d7039d4',
            'SOSGL',
            '1;2;0072/SGL/VIII/2026;-;2026-08-18;SJA;SEULAWAH JAYA;ZFS;ZULFAHMI SARAGI;BIAYA PEMBELIAN SPAREPART LUAR CSC;1305000;20957;',
            '2;2;0072/SGL/VIII/2026;200.09;D;200.09-1;HUTANG DAGANG CSC;-;1305000;-;0;0;0;20957;18643;CSC.SGL;',
            '1;2;0073/SGL/VIII/2026;-;2026-08-18;SPR;SIGLI PRINTING;ZFS;ZULFAHMI SARAGI;BIAYA CETAK SPANDUK;245000;20959;',
            '2;2;0073/SGL/VIII/2026;506;D;506-3;BIAYA PROMOSI ( CETAK SPANDUK );-;250000;-;0;0;0;20959;18645;CSC.SGL;',
            '2;2;0073/SGL/VIII/2026;900.03;;900.03-1;PPH PASAL 23 ;-;-5000;-;0;0;0;20959;18646;NA;',
        ]);

        $parser = new RkkParser();
        $this->assertTrue($parser->supports('SOSGL-260818-260818RKK.RKK'));

        $result = $parser->parse('SOSGL-260818-260818RKK.RKK', $content);

        $this->assertSame('rkk', $result->jenis);
        $this->assertSame('SOSGL', $result->unitUsahaCode);
        $this->assertSame('19f4c963a56b21c376cf7ba23d7039d4', $result->sourceHash);
        $rows = $result->rows['analisa_rkk_transactions'];
        $this->assertCount(3, $rows);

        $this->assertSame('0072/SGL/VIII/2026', $rows[0]['no_voucher']);
        $this->assertSame('2026-08-18', $rows[0]['tanggal']);
        $this->assertSame('200.09', $rows[0]['kode_akun']);
        $this->assertSame('HUTANG DAGANG CSC', $rows[0]['nama_akun']);
        $this->assertSame(1305000.0, $rows[0]['nominal']);
        $this->assertSame('SEULAWAH JAYA', $rows[0]['nama_supplier']);

        // Baris kedua voucher 0073 punya nominal negatif (koreksi PPh).
        $this->assertSame(-5000.0, $rows[2]['nominal']);
        $this->assertSame('0073/SGL/VIII/2026', $rows[2]['no_voucher']);
    }

    public function test_acc_parser_memisahkan_konsumen_dan_kontrak(): void
    {
        $content = implode("\r\n", [
            'SOSGL;2026-08-18;2026-08-18;',
            '0;SOSGL;BUDT010190;CASH;UMUM;BUDI TESTING;DUSUN TES;KEC TES;Kab. Tes;DS. TES;24183;081200000001;1;1990-01-01;-;081200000001;-;1107010101900001;JMH2E 0000000;BUDT010190;110905;Kec Tes;11090534;Ds Tes;2026-08-18;',
            '1;SOSGL;H00999-26;2026-08-18;0999/SGL/VIII/2026;;CJ000999;BUDT010190;BUDT010190;REG;18337838;2017162;25000000;2000000;0;0;0;0;0;0;0;0;2026-08-18;0.5;ABC;P;LANCAR;CASH;NA;NA;;-;0;',
            // Piutang belum lunas — bagian TERBESAR di data produksi nyata,
            // sengaja disertakan di sini karena field-nya sempat terlewat
            // saat eksplorasi awal (hanya baca potongan awal file).
            'F;SOSGL;2026-08-18;TESK010190;GAMPONG TES;081200000099;TESK010190;F00099-26;2026-06-04;MT1;3901351;0;0;0;;0000-00-00;0;3901351;0;0;0;0;3901351;2026-06-04;-;',
        ]);

        $parser = new AccParser();
        $this->assertTrue($parser->supports('SOSGL-20260818-20260818.ACC'));

        $result = $parser->parse('SOSGL-20260818-20260818.ACC', $content);

        $this->assertSame('acc', $result->jenis);
        $this->assertSame('SOSGL', $result->unitUsahaCode);

        $consumers    = $result->rows['analisa_acc_consumers'];
        $contracts    = $result->rows['analisa_acc_contracts'];
        $receivables  = $result->rows['analisa_acc_receivables'];
        $this->assertCount(1, $consumers);
        $this->assertCount(1, $contracts);
        $this->assertCount(1, $receivables);

        $this->assertSame('BUDT010190', $consumers[0]['kode_konsumen']);
        $this->assertSame('BUDI TESTING', $consumers[0]['nama']);
        $this->assertSame('1107010101900001', $consumers[0]['nik']);
        $this->assertSame('081200000001', $consumers[0]['no_hp']);

        $this->assertSame('H00999-26', $contracts[0]['no_bukti']);
        $this->assertSame('BUDT010190', $contracts[0]['kode_konsumen']);
        $this->assertSame(25000000.0, $contracts[0]['harga_otr']);
        $this->assertSame(2000000.0, $contracts[0]['dp']);
        $this->assertSame('LANCAR', $contracts[0]['status_kredit']);

        $this->assertSame('TESK010190', $receivables[0]['kode_konsumen']);
        $this->assertSame('F00099-26', $receivables[0]['no_bukti']);
        $this->assertSame('2026-06-04', $receivables[0]['tanggal_transaksi']);
        $this->assertSame('2026-08-18', $receivables[0]['tanggal_laporan']);
        $this->assertSame(3901351.0, $receivables[0]['nominal']);
    }

    public function test_lpk_parser_ekstrak_no_bukti_dan_faktur_walau_posisi_bergeser(): void
    {
        $content = implode("\r\n", [
            'd0a23a87f53fc7b0a42063a7d43529a8',
            '0;SOTDB;2026-08-25;',
            // Baris penjualan unit baru (PBBO) — no_bukti/faktur di posisi "biasa"
            '1;YK000001;280;BUDT010190;BUDI TESTING;IMFI;;0999/TDB/VIII/2026;H00151-26;0;3667000;;;0000-00-00;0;3667000;PBBO;1. Penjualan Unit Baru;P;;;',
            // Baris kwitansi gantung (CRGT) — kolom kosong lebih banyak, no_bukti bergeser posisi
            '1;YK000002;0;;;;H00061-26;;;0;22939000;;;0000-00-00;0;22939000;CRGT;3. Penerimaan Kwitansi Gantung;P;BUDI TESTING QQ IMFI;;',
        ]);

        $parser = new LpkParser();
        $this->assertTrue($parser->supports('SOTDB-260825LPK.LPK'));

        $result = $parser->parse('SOTDB-260825LPK.LPK', $content);

        $this->assertSame('lpk', $result->jenis);
        $this->assertSame('SOTDB', $result->unitUsahaCode);
        $this->assertSame('2026-08-25', $result->tanggal);

        $rows = $result->rows['analisa_lpk_penjualan'];
        $this->assertCount(2, $rows);

        $this->assertSame('H00151-26', $rows[0]['no_bukti']);
        $this->assertSame('0999/TDB/VIII/2026', $rows[0]['no_faktur']);
        $this->assertSame('PBBO', $rows[0]['kode_transaksi']);
        $this->assertSame(3667000.0, $rows[0]['nominal']);

        // Kwitansi gantung: no_bukti tetap ketemu walau posisi kolomnya beda.
        $this->assertSame('H00061-26', $rows[1]['no_bukti']);
        $this->assertNull($rows[1]['no_faktur']);
        $this->assertSame('CRGT', $rows[1]['kode_transaksi']);
        $this->assertSame(22939000.0, $rows[1]['nominal']);
    }

    public function test_parser_registry_pilih_parser_sesuai_ekstensi(): void
    {
        $registry = new ParserRegistry();

        $this->assertInstanceOf(RkkParser::class, $registry->find('SOSGL-260818-260818RKK.RKK'));
        $this->assertInstanceOf(AccParser::class, $registry->find('SOSGL-20260818-20260818.ACC'));
        $this->assertInstanceOf(LpkParser::class, $registry->find('SOTDB-260825LPK.LPK'));
        $this->assertNull($registry->find('berkas-tidak-dikenal.txt'));
    }
}
