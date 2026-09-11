<?php

namespace App\Services\WebPush;

/**
 * Web Push memakai base64url (RFC 4648 §5) di mana-mana: kunci VAPID, kunci
 * langganan dari browser, sampai header Authorization. Bedanya dengan base64
 * biasa cuma tiga: "+" jadi "-", "/" jadi "_", dan "=" di ekor dibuang.
 */
final class Base64Url
{
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Menerima bentuk dengan maupun tanpa padding — browser mengirim tanpa padding. */
    public static function decode(string $value): string
    {
        $sisa = strlen($value) % 4;
        if ($sisa !== 0) {
            $value .= str_repeat('=', 4 - $sisa);
        }

        $raw = base64_decode(strtr($value, '-_', '+/'), true);

        return $raw === false ? '' : $raw;
    }
}
