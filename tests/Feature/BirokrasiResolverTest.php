<?php

namespace Tests\Feature;

use App\Services\BirokrasiResolver;
use Tests\TestCase;

/**
 * Rute persetujuan rekomendasi (config/birokrasi.php). RKR = wilayah Kepri
 * (bukan cuma "sub-wilayah Retail Riau" umum) -- POS TBN & CSC TBN akhirnya
 * dikonfirmasi masuk RKR/Kepri (approver "Retail Riau"), setelah sempat
 * dipindah ke Retail Aceh lalu dikoreksi balik. AFFCO RAC/AFFCO RRI memakai
 * satu akun "Retail Affco" yang sama untuk keduanya. Grup SO/H1, CSC/H2,
 * WHS Unit, dan WHS Part diganti label step manajernya dari "Manajer IAT
 * DEPT" menjadi "Manajer Audit". WHS Unit & WHS Part dulu punya bug: ketiga
 * sub-grup wilayahnya (RRI/RKR/RAC) memakai daftar units yang identik, jadi
 * 2 dari 3 sub-grup jadi dead code -- sudah dipisah per wilayah. GJP1/GJP2
 * H1 dipindah dari SO/H1-RRI ke SO/H1-AFFCO RRI, dan HM KSP/HMS KSP
 * disatukan ke grup AFFCO RAC (H1 & H2).
 */
class BirokrasiResolverTest extends TestCase
{
    public function test_pos_tbn_dan_csc_tbn_masuk_rkr_kepri(): void
    {
        $this->assertSame('SO / H1 - RKR', BirokrasiResolver::groupFor('POS TBN'));
        $this->assertContains('Retail Riau', BirokrasiResolver::approversFor('POS TBN'));

        $this->assertSame('CSC / H2 - RKR', BirokrasiResolver::groupFor('CSC TBN'));
        $this->assertContains('Retail Riau', BirokrasiResolver::approversFor('CSC TBN'));
    }

    public function test_gjp_h1_masuk_affco_rri(): void
    {
        $this->assertSame('SO / H1 - AFFCO RRI', BirokrasiResolver::groupFor('GJP1 H1'));
        $this->assertSame('SO / H1 - AFFCO RRI', BirokrasiResolver::groupFor('GJP2 H1'));
    }

    public function test_pos_sk_masuk_rri(): void
    {
        $this->assertSame('SO / H1 - RRI', BirokrasiResolver::groupFor('POS SK'));
    }

    public function test_ksp_disatukan_ke_affco_rac(): void
    {
        $this->assertSame('SO / H1 - AFFCO RAC', BirokrasiResolver::groupFor('HM KSP'));
        $this->assertSame('CSC / H2 - AFFCO RAC', BirokrasiResolver::groupFor('HMS KSP'));
    }

    public function test_affco_rac_dan_affco_rri_sama_sama_pakai_retail_affco(): void
    {
        $this->assertContains('Retail Affco', BirokrasiResolver::approversFor('HM CND')); // AFFCO RAC
        $this->assertContains('Retail Affco', BirokrasiResolver::approversFor('HM PKU')); // AFFCO RRI
        $this->assertContains('Retail Affco', BirokrasiResolver::approversFor('HMS CND')); // CSC AFFCO RAC
        $this->assertContains('Retail Affco', BirokrasiResolver::approversFor('HMS MPY')); // CSC AFFCO RRI
    }

    /** Cabang lain di grup RKR/Retail Riau tidak ikut terpengaruh. */
    public function test_cabang_rkr_lain_tetap_retail_riau(): void
    {
        $this->assertContains('Retail Riau', BirokrasiResolver::approversFor('SO BKG'));
        $this->assertContains('Retail Riau', BirokrasiResolver::approversFor('CSC BKG'));
    }

    /** Step manajer di semua grup SO/H1 & CSC/H2 memakai label "Manajer Audit", bukan "Manajer IAT DEPT". */
    public function test_grup_so_dan_csc_memakai_label_manajer_audit(): void
    {
        foreach (config('birokrasi') as $groupName => $group) {
            if (!str_starts_with($groupName, 'SO / H1') && !str_starts_with($groupName, 'CSC / H2')) {
                continue;
            }
            $this->assertContains('Manajer Audit', $group['approvers'], "Grup \"{$groupName}\" belum pakai \"Manajer Audit\".");
            $this->assertNotContains('Manajer IAT DEPT', $group['approvers'], "Grup \"{$groupName}\" masih pakai label lama.");
        }
    }

    public function test_whs_unit_dan_whs_part_terpisah_per_wilayah(): void
    {
        $this->assertSame('WHS Unit - RRI', BirokrasiResolver::groupFor('WHS Unit ARK'));
        $this->assertSame('WHS Unit - RRI', BirokrasiResolver::groupFor('WHS Unit AKS'));
        $this->assertSame('WHS Unit - RKR', BirokrasiResolver::groupFor('WHS Unit RKR'));
        $this->assertSame('WHS Unit - RKR', BirokrasiResolver::groupFor('WHS Unit TPI'));
        $this->assertSame('WHS Unit - RAC', BirokrasiResolver::groupFor('WHS Unit KIM'));

        $this->assertSame('WHS Part - RRI', BirokrasiResolver::groupFor('WHS Part AVIAN'));
        $this->assertSame('WHS Part - RKR', BirokrasiResolver::groupFor('WHS Part TPI'));
        $this->assertSame('WHS Part - RKR', BirokrasiResolver::groupFor('WHS Part RKR'));
        $this->assertSame('WHS Part - RAC', BirokrasiResolver::groupFor('WHS Part KIM'));
    }

    /** Tidak ada unit WHS Unit/WHS Part yang terdaftar di lebih dari satu grup wilayah. */
    public function test_unit_whs_tidak_terdaftar_dobel(): void
    {
        $seen = [];
        foreach (config('birokrasi') as $groupName => $group) {
            if (!str_starts_with($groupName, 'WHS Unit') && !str_starts_with($groupName, 'WHS Part')) {
                continue;
            }
            foreach ($group['units'] as $unit) {
                $key = strtolower(trim($unit));
                $this->assertArrayNotHasKey($key, $seen, "\"{$unit}\" terdaftar di lebih dari satu grup WHS.");
                $seen[$key] = $groupName;
            }
        }
    }
}
