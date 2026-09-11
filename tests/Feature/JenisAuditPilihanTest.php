<?php

namespace Tests\Feature;

use App\Http\Controllers\PlanAuditPdfController;
use ReflectionClass;
use Tests\TestCase;

/**
 * Daftar "Jenis Audit" hidup di tiga tempat: dropdown pada form Plan Audit,
 * dropdown yang sama pada halaman Database (konfigurasi checklist per jenis),
 * dan peta kalimat tugas di SPT. Ketiganya gampang bergeser sendiri-sendiri:
 * jenis baru ditambahkan di form, lalu SPT-nya jatuh ke kalimat generik dan
 * checklist-nya tidak bisa dikonfigurasi. Tes ini mengunci ketiganya agar
 * tetap satu daftar.
 */
class JenisAuditPilihanTest extends TestCase
{
    /** @return list<string> */
    private function opsiDropdown(string $view, string $idSelect): array
    {
        $html = file_get_contents(resource_path("views/akta/pages/{$view}.blade.php"));

        $awal = strpos($html, "id=\"{$idSelect}\"");
        $this->assertNotFalse($awal, "Select #{$idSelect} tidak ditemukan di {$view}.blade.php");

        $akhir = strpos($html, '</select>', $awal);
        $this->assertNotFalse($akhir, "Select #{$idSelect} tidak ditutup di {$view}.blade.php");

        preg_match_all(
            '/<option value="([^"]*)"/',
            substr($html, $awal, $akhir - $awal),
            $cocok
        );

        return array_map(html_entity_decode(...), $cocok[1]);
    }

    /** @return list<string> */
    private function jenisBerkalimatTugas(): array
    {
        $peta = (new ReflectionClass(PlanAuditPdfController::class))
            ->getConstant('DESKRIPSI_TUGAS');

        return array_keys($peta);
    }

    public function test_dua_dropdown_jenis_audit_isinya_sama(): void
    {
        $this->assertSame(
            $this->opsiDropdown('plan-audit', 'jenisAudit'),
            $this->opsiDropdown('database', 'atcJenisAuditInput'),
            'Dropdown Jenis Audit di form Plan Audit dan di halaman Database harus persis sama, '
            .'kalau tidak ada jenis yang checklist-nya tidak bisa dikonfigurasi.'
        );
    }

    public function test_setiap_jenis_audit_punya_kalimat_tugas_di_spt(): void
    {
        $tanpaKalimat = array_diff($this->opsiDropdown('plan-audit', 'jenisAudit'), $this->jenisBerkalimatTugas());

        $this->assertSame([], array_values($tanpaKalimat),
            'Jenis audit berikut belum punya kalimat tugas di PlanAuditPdfController::DESKRIPSI_TUGAS, '
            .'jadi SPT-nya akan memakai kalimat generik: '.implode(', ', $tanpaKalimat));
    }

    public function test_tidak_ada_kalimat_tugas_untuk_jenis_yang_sudah_tidak_ada(): void
    {
        $yatim = array_diff($this->jenisBerkalimatTugas(), $this->opsiDropdown('plan-audit', 'jenisAudit'));

        $this->assertSame([], array_values($yatim),
            'Kalimat tugas berikut menunjuk jenis audit yang tidak ada lagi di dropdown: '.implode(', ', $yatim));
    }

    public function test_dua_gudang_baru_bisa_dipilih(): void
    {
        $opsi = $this->opsiDropdown('plan-audit', 'jenisAudit');

        $this->assertContains('Audit Warehouse PART', $opsi);
        $this->assertContains('Audit Warehouse UNIT', $opsi);
    }
}
