<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /** GET /api/profile — data akun user yang sedang login. */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'ok'   => true,
            'data' => $this->format($request->user()),
        ]);
    }

    /** PUT /api/profile — perbarui identitas (nama, display name, email). */
    public function update(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();

        $payload = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'email'        => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->fill([
            'name'         => $payload['name'],
            'display_name' => $payload['display_name'] ?: $payload['name'],
            'email'        => $payload['email'] ?: $user->email,
        ])->save();

        $logger->write($request, 'PROFILE_UPDATE', 'profile', 'Update identitas akun sendiri', $user);

        return response()->json([
            'ok'      => true,
            'message' => 'Identitas akun berhasil diperbarui.',
            'data'    => $this->format($user->fresh()),
        ]);
    }

    /** POST /api/profile/photo — unggah / ganti foto profil. */
    public function uploadPhoto(Request $request, ActivityLogger $logger): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Hapus foto lama agar tidak menumpuk.
        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        $user->update(['photo_path' => $path]);

        $logger->write($request, 'PROFILE_PHOTO', 'profile', 'Mengubah foto profil', $user);

        return response()->json([
            'ok'      => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data'    => $this->format($user->fresh()),
        ]);
    }

    /** DELETE /api/profile/photo — hapus foto profil. */
    public function deletePhoto(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->update(['photo_path' => null]);

        $logger->write($request, 'PROFILE_PHOTO_DELETE', 'profile', 'Menghapus foto profil', $user);

        return response()->json([
            'ok'      => true,
            'message' => 'Foto profil berhasil dihapus.',
            'data'    => $this->format($user->fresh()),
        ]);
    }

    /** PUT /api/profile/password — ganti password (wajib password lama). */
    public function changePassword(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();

        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($payload['current_password'], $user->password)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Password lama salah.',
                'errors'  => ['current_password' => ['Password lama salah.']],
            ], 422);
        }

        $user->password       = Hash::make($payload['password']);
        $user->plain_password = $payload['password'];
        $user->save();

        // Cabut token lain (perangkat lain), pertahankan sesi saat ini.
        $currentId = $request->user()->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn($q) => $q->where('id', '!=', $currentId))->delete();

        $logger->write($request, 'PROFILE_PASSWORD', 'profile', 'Mengganti password sendiri', $user);

        return response()->json([
            'ok'      => true,
            'message' => 'Password berhasil diganti.',
        ]);
    }

    private function format($user): array
    {
        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'name'        => $user->name,
            'displayName' => $user->display_name,
            'email'       => $user->email,
            'photoUrl'    => $user->photo_url,
            'role'        => $user->role,
            'unitUsaha'   => $user->unit_usaha ?: '',
            'wilayah'     => $user->wilayah ?: '',
        ];
    }
}
