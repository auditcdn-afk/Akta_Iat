<?php

namespace App\Console\Commands;

use App\Models\DbUnitUsaha;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateUsersFromUnitUsaha extends Command
{
    /**
     * Contoh:
     *   php artisan akta:generate-users
     *   php artisan akta:generate-users --password=rahasia123
     */
    protected $signature = 'akta:generate-users
        {--password=12345678 : Password default untuk akun baru}
        {--keep-existing-password : Jangan reset password untuk user yang sudah ada}
        {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Buat akun pengguna dari seluruh data unit usaha (role diambil dari jenis, diurutkan per wilayah).';

    /** Palet warna badge untuk role yang dibuat otomatis. */
    private array $palette = ['blue', 'green', 'amber', 'purple', 'red', 'slate'];

    public function handle(): int
    {
        $password     = (string) $this->option('password');
        $dryRun       = (bool) $this->option('dry-run');
        $keepPassword = (bool) $this->option('keep-existing-password');

        // Urutkan berdasarkan wilayah lalu nama unit usaha.
        $units = DbUnitUsaha::query()
            ->whereNotNull('unit_usaha')
            ->where('unit_usaha', '!=', '')
            ->orderBy('wilayah')
            ->orderBy('unit_usaha')
            ->get();

        if ($units->isEmpty()) {
            $this->warn('Tabel db_unit_usaha kosong. Import data unit usaha terlebih dahulu.');
            return self::FAILURE;
        }

        // 1) Pastikan role untuk setiap jenis tersedia.
        $roleMap = $this->ensureRoles($units->pluck('jenis')->filter()->unique()->values()->all(), $dryRun);

        $created = 0;
        $updated = 0;
        $rows    = [];

        foreach ($units as $unit) {
            $jenis   = trim((string) $unit->jenis);
            $role    = $jenis !== '' ? ($roleMap[$jenis] ?? Str::slug($jenis, '_')) : 'viewer';
            $username = $this->uniqueUsername($unit->unit_usaha);

            $rows[] = [$unit->wilayah ?: '-', $unit->unit_usaha, $jenis ?: '-', $role, $username];

            if ($dryRun) {
                continue;
            }

            $existing = User::query()->where('username', $username)->first();

            $attributes = [
                'name'         => $unit->unit_usaha,
                'display_name' => $unit->unit_usaha,
                'email'        => $username . '@akta.local',
                'role'         => $role,
                'unit_usaha'   => $unit->unit_usaha,
                'wilayah'      => $unit->wilayah,
                'is_disabled'  => false,
                'created_by'   => 'system:generate-users',
            ];

            if ($existing) {
                $existing->fill($attributes);
                // Reset password ke default agar konsisten (kecuali diminta dipertahankan).
                if (! $keepPassword) {
                    $existing->password       = Hash::make($password);
                    $existing->plain_password = $password;
                    $existing->tokens()->delete();
                }
                $existing->save();
                $updated++;
            } else {
                User::query()->create($attributes + [
                    'username'       => $username,
                    'password'       => Hash::make($password),
                    'plain_password' => $password,
                ]);
                $created++;
            }
        }

        $this->table(['Wilayah', 'Unit Usaha', 'Jenis', 'Role', 'Username'], $rows);

        if ($dryRun) {
            $this->info('[dry-run] ' . count($rows) . ' akun akan dibuat/diperbarui. Tidak ada perubahan disimpan.');
            return self::SUCCESS;
        }

        $this->info("Selesai. Dibuat: {$created}, diperbarui: {$updated}. Password default akun baru: {$password}");
        return self::SUCCESS;
    }

    /**
     * Pastikan setiap jenis punya role di tabel roles.
     * Mengembalikan map: jenis => role slug.
     */
    private function ensureRoles(array $jenisList, bool $dryRun): array
    {
        $map   = [];
        $order = (int) (Role::max('order') ?? 0);
        $i     = 0;

        foreach ($jenisList as $jenis) {
            $slug = Str::slug($jenis, '_') ?: 'role_' . md5($jenis);
            $map[$jenis] = $slug;

            if ($dryRun) {
                continue;
            }

            Role::query()->firstOrCreate(
                ['name' => $slug],
                [
                    'label'       => strtoupper($jenis),
                    'color'       => $this->palette[$i % count($this->palette)],
                    'description' => 'Dibuat otomatis dari jenis unit usaha: ' . $jenis,
                    'is_system'   => false,
                    'order'       => ++$order,
                ]
            );
            $i++;
        }

        return $map;
    }

    /** Buat username unik berbasis nama unit usaha (huruf kecil, underscore). */
    private function uniqueUsername(string $unitUsaha): string
    {
        $base = Str::slug($unitUsaha, '_') ?: 'unit';
        $username = $base;
        $n = 1;

        while (User::query()->where('username', $username)->exists()) {
            // Jika user yang ada berasal dari unit usaha yang sama, pakai username itu (idempotent).
            $candidate = User::query()->where('username', $username)->first();
            if ($candidate && $candidate->unit_usaha === $unitUsaha) {
                return $username;
            }
            $username = $base . '_' . (++$n);
        }

        return $username;
    }
}
