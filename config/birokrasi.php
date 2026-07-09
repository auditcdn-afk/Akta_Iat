<?php

/**
 * Birokrasi approval chain per unit usaha.
 *
 * Alur pengisian rekomendasi:
 *   Auditor (buat) → [approvers berurutan] → Manajer IAT DEPT → AFD (Keputusan AFD) → selesai
 *
 * Keputusan AFD adalah tahap final setelah Manajer IAT DEPT menyetujui,
 * hanya bisa diisi oleh user dengan role/unit_usaha "AFD".
 *
 * Setiap entry:
 *   'approvers' => daftar berurutan pihak yang harus mengisi keputusan.
 *                  Nilai cocok ke: role user (case-insensitive) ATAU unit_usaha user.
 *   'units'     => daftar nama cabang yang termasuk grup ini.
 *
 * Wilayah:
 *   RAC  = Retail Aceh
 *   RRI  = Retail Riau
 *   RKR  = Retail Riau (sub-wilayah)
 *   HO   = Head Office
 */
return [

    // ── WHS Unit ────────────────────────────────────────────────────────
    'WHS Unit - RRI' => [
        'approvers' => ['WHS', 'FIN REG', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Unit KIM', 'WHS Unit ARK', 'WHS Unit AKS', 'WHS Unit RKR', 'WHS Unit TPI'],
    ],
    'WHS Unit - RKR' => [
        'approvers' => ['WHS', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Unit KIM', 'WHS Unit ARK', 'WHS Unit AKS', 'WHS Unit RKR', 'WHS Unit TPI'],
    ],
    'WHS Unit - RAC' => [
        'approvers' => ['WHS', 'PAV', 'FIN DEPT', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Unit KIM', 'WHS Unit ARK', 'WHS Unit AKS', 'WHS Unit RKR', 'WHS Unit TPI'],
    ],

    // ── WHS Part ────────────────────────────────────────────────────────
    'WHS Part - RKR' => [
        'approvers' => ['WHS', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Part TPI', 'WHS Part KIM', 'WHS Part AVIAN', 'WHS Part RKR'],
    ],
    'WHS Part - RRI' => [
        'approvers' => ['WHS', 'FIN REG', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Part TPI', 'WHS Part KIM', 'WHS Part AVIAN', 'WHS Part RKR'],
    ],
    'WHS Part - RAC' => [
        'approvers' => ['WHS', 'PART Dept', 'FIN DEPT', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['WHS Part TPI', 'WHS Part KIM', 'WHS Part AVIAN', 'WHS Part RKR'],
    ],

    // ── FKT ─────────────────────────────────────────────────────────────
    'FKT - RRI' => [
        'approvers' => ['FIN REG', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['FIN DEPT FKT', 'RKR FKT', 'RRI FKT'],
    ],
    'FKT - RKR' => [
        'approvers' => ['REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['FIN DEPT FKT', 'RKR FKT', 'RRI FKT'],
    ],
    'FKT - HO' => [
        'approvers' => ['FIN DEPT', 'REG HEAD', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['FIN DEPT FKT', 'RKR FKT', 'RRI FKT'],
    ],

    // ── PAV ─────────────────────────────────────────────────────────────
    'PAV - HO' => [
        'approvers' => ['PAV', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['PAV'],
    ],

    // ── HC3 ─────────────────────────────────────────────────────────────
    'HC3 - HO' => [
        'approvers' => ['HC3 DEPT', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['HC3'],
    ],

    // ── SO / H1 ─────────────────────────────────────────────────────────
    // Alur: Auditor → SO → Retail [wilayah] → Manajer IAT DEPT
    // SO  : diisi oleh user dengan role "so"
    // Retail [wilayah] : diisi oleh user dengan unit_usaha sesuai wilayah
    // ─────────────────────────────────────────────────────────────────────
    'SO / H1 - RRI' => [
        'approvers' => ['SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'SO ARK', 'SO SDR', 'SO TBS', 'SO SKH', 'SO BKN', 'SO KPR', 'SO UBT', 'SO SRM',
            'SO LPK', 'SO FLB', 'POS PKC', 'SO AMK', 'SO DRI', 'SO GRG', 'SO PGR', 'SO SLP',
            'SO DMI', 'SO BBT', 'POS PJD', 'SO UJT', 'SO KAN', 'SO PRW', 'SO TBH', 'POS SPK',
            'GJP1 H1', 'GJP2 H1',
        ],
    ],
    'SO / H1 - RKR' => [
        'approvers' => ['SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['SO BKG', 'SO NGY', 'SO MKG', 'SO BTC', 'POS TBN', 'SO TPI', 'POS NTN', 'DIP H1'],
    ],
    'SO / H1 - RAC' => [
        'approvers' => ['SO', 'Retail Aceh', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'SO TPP', 'SO TDB', 'SO SGL', 'SO BNN', 'SO TKN', 'SO LSM', 'SO LGS', 'SO MBO',
            'SO ALB', 'SO SPP', 'SO KTC', 'POS JNB', 'POS BKJ', 'POS SML', 'POS TTN',
            'SO BDS', 'POS AGL', 'POS SBS',
        ],
    ],
    'SO / H1 - AFFCO RAC' => [
        'approvers' => ['SO', 'Retail Aceh', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['HM CND', 'HM TKU', 'SBG MTR', 'KPM MBO', 'KPM SUO', 'KPM RMO', 'LBS H1'],
    ],
    'SO / H1 - AFFCO RRI' => [
        'approvers' => ['SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'HM PKU', 'HM MPY', 'HM PKC', 'HM UKI', 'HM LBD',
            'KPM PBR', 'KPM SRK', 'KPM SIK', 'CVKJ H1', 'CVSK H1', 'TUKJY H1',
        ],
    ],

    // ── CSC / H2 ────────────────────────────────────────────────────────
    // Alur: Auditor → CSC → SO → Retail [wilayah] → Manajer IAT DEPT
    // ─────────────────────────────────────────────────────────────────────
    'CSC / H2 - RRI' => [
        'approvers' => ['CSC', 'SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'CSC ARK', 'CSC SDR', 'CSC TBS', 'CSC SKH', 'CSC BKN', 'CSC LPK', 'CSC FLB',
            'CSC KPR', 'CSC UBT', 'CSC PKC', 'CSC DRI', 'CSC GRG', 'CSC PGR', 'CSC SLP',
            'CSC BBT', 'CSC UJT', 'CSC KAN', 'CSC PRW', 'CSC AMK', 'CSC SRM', 'CSC PJD',
            'CSC SPK', 'CSC TBH', 'GJP1 H2', 'GJP2 H2',
        ],
    ],
    'CSC / H2 - RKR' => [
        'approvers' => ['CSC', 'SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['DIP H2', 'CSC BKG', 'CSC NGY', 'CSC MKG', 'CSC BTC', 'CSC TPI', 'CSC TBN', 'CSC NTN'],
    ],
    'CSC / H2 - RAC' => [
        'approvers' => ['CSC', 'SO', 'Retail Aceh', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'CSC TPP', 'CSC TDB', 'CSC SGL', 'CSC LGS', 'CSC MBO', 'CSC ALB', 'CSC SPP',
            'CSC BKJ', 'CSC SML', 'CSC BNN', 'CSC TKN', 'CSC LSM', 'CSC TTN', 'CSC AGL', 'CSC SBS',
        ],
    ],
    'CSC / H2 - AFFCO RAC' => [
        'approvers' => ['CSC', 'SO', 'Retail Aceh', 'Manajer IAT DEPT', 'AFD'],
        'units'     => ['HMS CND', 'KMS RMO', 'KMS MBO', 'LBS H2', 'SBG SRV'],
    ],
    'CSC / H2 - AFFCO RRI' => [
        'approvers' => ['CSC', 'SO', 'Retail Riau', 'Manajer IAT DEPT', 'AFD'],
        'units'     => [
            'HMS MPY', 'KMS PBR', 'KMS SRK', 'KMS SIK', 'CVKJ H2', 'CVSK H2',
            'HMS PKC', 'HMS LBD', 'TUKJY H2', 'DIP H2', 'HMS KSP',
        ],
    ],

];
