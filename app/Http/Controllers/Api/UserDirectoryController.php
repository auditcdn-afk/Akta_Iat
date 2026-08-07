<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar pengguna versi ringkas untuk mengisi dropdown/datalist di berbagai
 * halaman (PICA, distribusi SK, pinjaman cabang).
 *
 * Sebelumnya ketiga endpoint ini ditulis sebagai closure di routes/api.php.
 * Laravel memang bisa men-cache rute berisi closure (lewat SerializableClosure),
 * tapi isinya ikut ter-serialisasi ke dalam berkas cache dan harus di-unserialize
 * saat boot. Referensi controller jauh lebih ringan, dan logikanya jadi bisa
 * diuji terpisah dari berkas rute.
 */
class UserDirectoryController extends Controller
{
    /** Label nama pengguna untuk datalist bebas-ketik. */
    public function names(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['name', 'username', 'unit_usaha']);

        return response()->json([
            'data' => $users->map(fn(User $u) => [
                'label' => ($u->name ?: $u->username)
                    . ($u->unit_usaha ? ' (' . $u->unit_usaha . ')' : ''),
            ]),
        ]);
    }

    /** Username + label untuk pemilihan distribusi SK dan sejenisnya. */
    public function options(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['username', 'name', 'display_name', 'role', 'unit_usaha']);

        return response()->json([
            'data' => $users->map(fn(User $u) => [
                'username' => $u->username,
                'label' => ($u->display_name ?: $u->name ?: $u->username)
                    . ' (' . ($u->role ?: '-') . ($u->unit_usaha ? ' • ' . $u->unit_usaha : '') . ')',
            ]),
        ]);
    }

    /** Opsi unit usaha berdasarkan role — dropdown pinjaman cabang dll. */
    public function unitUsahaByRole(Request $request): JsonResponse
    {
        $role = $request->query('role', 'h1');

        $options = User::query()
            ->where('role', $role)
            ->whereNotNull('unit_usaha')
            ->where('unit_usaha', '!=', '')
            ->where('is_disabled', false)
            ->orderBy('unit_usaha')
            ->pluck('unit_usaha')
            ->unique()
            ->values();

        return response()->json(['data' => $options]);
    }
}
