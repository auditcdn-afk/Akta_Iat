<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SIMPAS-IAT Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi khusus aplikasi SIMPAS-IAT.
    | Project lama memakai Express + JSONB hybrid.
    | Di Laravel versi lokal ini kita pakai MySQL JSON.
    |
    */

    'app_name' => env('AKTA_APP_NAME', 'SIMPAS-IAT'),

    'timezone' => env('AKTA_TIMEZONE', 'Asia/Jakarta'),

    'default_role' => env('AKTA_DEFAULT_ROLE', 'auditor'),

    'token_name' => env('AKTA_TOKEN_NAME', 'akta-iat-token'),

    'data_cache_ttl' => env('AKTA_DATA_CACHE_TTL', 60),

    'roles' => [
        'admin',
        'manajer',
        'koordinator',
        'auditor',
        'viewer',
        'coo',
        'h1',
        'h2',
        'bpk',
        'unit',
    ],

];
