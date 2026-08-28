<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fitur Analisa Zona berisi data pribadi konsumen (NIK/HP/alamat) — sengaja
 * digerbang lewat flag terpisah `users.analisa_zona_access`, BUKAN lewat
 * kolom `role` (satu user cuma boleh 1 role, mengubahnya akan menghapus
 * akses role auditor/admin yang sedang dipakai). Lihat catatan di migrasi
 * `add_analisa_zona_access_to_users_table`.
 */
class EnsureAnalisaZonaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->is_disabled) {
            return response()->json(['message' => 'Akun ini dinonaktifkan.'], 403);
        }

        if (! $user->canAnalisaZona()) {
            return response()->json(['message' => 'Akses ditolak. Fitur ini dibatasi untuk tim analisa yang ditunjuk.'], 403);
        }

        return $next($request);
    }
}
