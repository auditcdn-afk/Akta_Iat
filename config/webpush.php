<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kunci VAPID
    |--------------------------------------------------------------------------
    |
    | Dibuat sekali dengan `php artisan akta:vapid-keys`, lalu disalin ke .env
    | di server. Selama kosong, seluruh fitur push mati total dan aplikasi
    | berjalan persis seperti sebelumnya — jadi men-deploy kode ini ke server
    | yang belum disiapkan tidak mengubah apa pun.
    |
    | JANGAN mengganti kunci privat setelah pengguna berlangganan: semua
    | langganan lama akan ditolak server push dan harus diaktifkan ulang.
    |
    */

    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),

    /*
    | Kontak yang bisa dihubungi server push kalau ada masalah dengan kiriman
    | kita. Wajib berupa "mailto:" atau "https:" (RFC 8292 §2.1).
    */
    'subject' => env('VAPID_SUBJECT', 'mailto:auditcdn@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Antrean
    |--------------------------------------------------------------------------
    |
    | Pengiriman push SELALU lewat antrean, tidak pernah di dalam permintaan
    | yang sedang dilayani pengguna. Satu approve bisa menghasilkan belasan
    | kiriman HTTPS ke server push (satu per perangkat penerima); kalau itu
    | dikerjakan inline, tombol Approve akan terasa menggantung beberapa detik.
    |
    | Koneksinya disebut eksplisit — bukan sekadar default aplikasi — supaya
    | .env produksi yang kebetulan masih QUEUE_CONNECTION=sync tidak diam-diam
    | menarik pengiriman itu kembali ke dalam permintaan pengguna.
    |
    */

    'queue_connection' => env('WEBPUSH_QUEUE_CONNECTION', 'database'),
    'queue' => env('WEBPUSH_QUEUE', 'default'),

    /*
    | Batas waktu satu permintaan ke server push. Dipasang pendek: job ini
    | berjalan di latar belakang, dan endpoint yang menggantung tidak boleh
    | menahan antrean notifikasi lain.
    */
    'timeout' => (int) env('WEBPUSH_TIMEOUT', 10),

    /*
    | Berapa lama server push menyimpan notifikasi bila perangkat sedang mati
    | atau offline. 12 jam: cukup untuk yang mematikan HP semalam, tapi tidak
    | sampai memunculkan notifikasi basi berhari-hari kemudian.
    */
    'ttl' => (int) env('WEBPUSH_TTL', 43200),

];
