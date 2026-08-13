<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KaryawanController extends Controller
{
    // Role kantor pusat (HO) yang boleh MELIHAT data karyawan semua unit usaha
    // (mis. untuk keperluan audit). Menambah/menghapus tetap dibatasi lebih
    // ketat lagi, lihat ensureCanWrite().
    private const HO_ROLES = ['admin', 'manajer', 'auditor', 'koordinator', 'coo'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isHo = in_array($user?->role, self::HO_ROLES, true);

        $query = Karyawan::query()->latest('id');

        if ($isHo) {
            if ($request->filled('unit_usaha')) {
                $query->where('unit_usaha', $request->query('unit_usaha'));
            }
        } else {
            $query->where('unit_usaha', $user?->unit_usaha);
        }

        $karyawans = $query->get()->map(fn(Karyawan $k) => $k->toAktaArray());

        return response()->json(['ok' => true, 'data' => $karyawans]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user?->role === 'admin';

        $data = $request->validate([
            'unit_usaha' => [$isAdmin ? 'required' : 'nullable', 'string', 'max:150'],
            'nama'       => ['required', 'string', 'max:150'],
            'jabatan'    => ['required', 'string', 'max:150'],
            'foto'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Non-admin selalu menambah untuk unit usahanya sendiri -- nilai
        // unit_usaha dari klien (kalaupun ada) diabaikan supaya tidak bisa
        // menambah data atas nama cabang lain.
        $unitUsaha = $isAdmin ? $data['unit_usaha'] : $user?->unit_usaha;

        if (!$unitUsaha) {
            return response()->json([
                'ok' => false,
                'message' => 'Akun Anda belum terhubung ke unit usaha manapun.',
            ], 403);
        }

        $photoPath = null;
        if ($request->hasFile('foto')) {
            $photoPath = $request->file('foto')->store('karyawan', 'public');
        }

        $karyawan = Karyawan::query()->create([
            'unit_usaha' => $unitUsaha,
            'nama'       => $data['nama'],
            'jabatan'    => $data['jabatan'],
            'photo_path' => $photoPath,
            'created_by' => $user?->username,
        ]);

        $logger->write($request, 'KARYAWAN_CREATE', 'karyawans',
            "Tambah karyawan \"{$karyawan->nama}\" ({$unitUsaha})", $user);

        return response()->json([
            'ok' => true,
            'message' => 'Data karyawan berhasil ditambahkan.',
            'data' => $karyawan->toAktaArray(),
        ], 201);
    }

    public function destroy(Request $request, Karyawan $karyawan, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanWrite($request, $karyawan->unit_usaha);

        if ($karyawan->photo_path && Storage::disk('public')->exists($karyawan->photo_path)) {
            Storage::disk('public')->delete($karyawan->photo_path);
        }

        $nama = $karyawan->nama;
        $unitUsaha = $karyawan->unit_usaha;
        $karyawan->delete();

        $logger->write($request, 'KARYAWAN_DELETE', 'karyawans',
            "Hapus karyawan \"{$nama}\" ({$unitUsaha})", $user);

        return response()->json(['ok' => true, 'message' => 'Data karyawan berhasil dihapus.']);
    }

    // Hanya admin, atau akun unit usaha yang sama, yang boleh menambah/menghapus.
    private function ensureCanWrite(Request $request, string $unitUsaha): void
    {
        $user = $request->user();
        $isAdmin = $user?->role === 'admin';
        $isOwnUnit = $user?->unit_usaha && $user->unit_usaha === $unitUsaha;

        abort_unless($isAdmin || $isOwnUnit, 403, 'Anda tidak berwenang mengubah data karyawan unit usaha ini.');
    }
}
