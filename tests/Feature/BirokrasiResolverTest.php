<?php

namespace Tests\Feature;

use App\Services\BirokrasiResolver;
use Tests\TestCase;

/**
 * Rute persetujuan rekomendasi (config/birokrasi.php) sempat salah wilayah:
 * POS TBN & CSC TBN ter-daftar di grup RKR (approver "Retail Riau"), padahal
 * cabang itu seharusnya "Retail Aceh". Dan AFFCO RAC/AFFCO RRI dulu memakai
 * approver "Retail Aceh"/"Retail Riau" biasa — sekarang disatukan jadi satu
 * akun "Retail Affco" untuk keduanya. Grup CSC/H2 juga diganti label step
 * manajernya dari "Manajer IAT DEPT" menjadi "Manajer Audit".
 */
class BirokrasiResolverTest extends TestCase
{
    public function test_pos_tbn_dan_csc_tbn_masuk_retail_aceh(): void
    {
        $this->assertSame('SO / H1 - RAC', BirokrasiResolver::groupFor('POS TBN'));
        $this->assertContains('Retail Aceh', BirokrasiResolver::approversFor('POS TBN'));

        $this->assertSame('CSC / H2 - RAC', BirokrasiResolver::groupFor('CSC TBN'));
        $this->assertContains('Retail Aceh', BirokrasiResolver::approversFor('CSC TBN'));
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

    /** Step manajer di semua grup CSC/H2 memakai label "Manajer Audit", bukan "Manajer IAT DEPT". */
    public function test_grup_csc_memakai_label_manajer_audit(): void
    {
        foreach (config('birokrasi') as $groupName => $group) {
            if (!str_starts_with($groupName, 'CSC / H2')) {
                continue;
            }
            $this->assertContains('Manajer Audit', $group['approvers'], "Grup \"{$groupName}\" belum pakai \"Manajer Audit\".");
            $this->assertNotContains('Manajer IAT DEPT', $group['approvers'], "Grup \"{$groupName}\" masih pakai label lama.");
        }
    }
}
