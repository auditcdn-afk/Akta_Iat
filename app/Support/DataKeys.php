<?php

namespace App\Support;

final class DataKeys
{
    public const KEYS = [
        'akta_plans',
        'akta_tasks',
        'akta_rekomendasi',
        'akta_pica',
        'akta_sk',
        'akta_sk_realisasi',
        'akta_pulsa',
        'akta_mobil_dinas',

        'akta_smh_onhand',
        'akta_smh_perlengkapan',
        'akta_smh_plafon',
        'akta_smh_unit_area',
        'akta_smh_harga',
        'akta_smh_hasil',
        'akta_smh_luar_fisik',

        'akta_pemeriksaan_kas',
        'akta_pemeriksaan_bank',
        'akta_piutang_reg',
        'akta_meterai',
        'akta_bpkb_db',
        'akta_bpkb_scan',
        'akta_hgp_stok',
        'akta_hgp_hasil',
        'akta_hgp_wo',
        'akta_kwt_db',
        'akta_kwt_scan',
        'akta_mt_database',
        'akta_mt_pemeriksaan',
        'akta_grading_db',

        'akta_bu_performance',
        'akta_mandiri_pengecekan',
        'akta_sertijab',

        'akta_users',
        'akta_notifications',
        'akta_menu_config',
        'akta_settings',
    ];

    public const OBJECT_KEYS = [
        'akta_menu_config',
        'akta_settings',
    ];

    public static function all(): array
    {
        return self::KEYS;
    }

    public static function allowed(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function isObjectKey(string $key): bool
    {
        return in_array($key, self::OBJECT_KEYS, true);
    }

    public static function defaultValue(string $key): array
    {
        return self::isObjectKey($key) ? [] : [];
    }
}
