<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class BirokrasiResolver
{
    /** Return approver roles for the given cabang, or [] if not found. */
    public static function approversFor(string $cabang): array
    {
        $cabangTrim = trim($cabang);
        foreach (config('birokrasi', []) as $group) {
            foreach ($group['units'] as $unit) {
                if (strcasecmp($unit, $cabangTrim) === 0) {
                    return $group['approvers'];
                }
            }
        }
        return [];
    }

    /** Return the group name for a cabang, or null. */
    public static function groupFor(string $cabang): ?string
    {
        $cabangTrim = trim($cabang);
        foreach (config('birokrasi', []) as $groupName => $group) {
            foreach ($group['units'] as $unit) {
                if (strcasecmp($unit, $cabangTrim) === 0) {
                    return $groupName;
                }
            }
        }
        return null;
    }

    /** Build initial steps array for a new recommendation. */
    public static function buildSteps(string $cabang, string $createdBy): array
    {
        $steps = [[
            'step'   => 'created',
            'role'   => null,
            'status' => 'done',
            'user'   => $createdBy,
            'time'   => now()->toDateTimeString(),
            'note'   => 'Rekomendasi dibuat oleh auditor.',
        ]];

        foreach (static::approversFor($cabang) as $role) {
            $steps[] = [
                'step'   => $role,
                'role'   => $role,
                'status' => 'pending',
                'user'   => null,
                'time'   => null,
                'note'   => null,
            ];
        }

        return $steps;
    }

    /** Return all groups with their units (for API). */
    public static function allGroups(): array
    {
        return config('birokrasi', []);
    }

    /**
     * User(s) yang seharusnya diberi notifikasi karena gilirannya mengisi
     * step $stepRole pada rekomendasi cabang $cabang. Sengaja TIDAK memakai
     * aturan "bypass internal" (admin/manajer/auditor boleh isi step apa
     * pun) yang dipakai AuditRecommendationController::approveStep() untuk
     * OTORISASI -- kalau dipakai di sini semua manajer/auditor akan dapat
     * notifikasi untuk setiap step di seluruh sistem. Di sini yang dicari
     * cuma penerima yang benar-benar dituju step ini:
     *   - role atau unit_usaha user cocok persis dengan nama step, ATAU
     *   - untuk step generik jenis unit (SO/CSC/WHS dst.), akun unit usaha
     *     itu sendiri (mis. unit_usaha "SO ALB" untuk step "SO"), ATAU
     *   - untuk step "Manajer Audit": role slug aslinya "manajer" (label
     *     tampilannya beda dari nilai step ini), jadi user role manajer
     *     ikut dianggap tujuan.
     * Kalau tidak ada satu pun yang cocok, jatuhkan ke admin supaya
     * notifikasi tidak hilang begitu saja (mis. belum ada akun "AFD" yang
     * dibuat).
     */
    public static function recipientsForStep(string $stepRole, string $cabang): Collection
    {
        $roleUpper = strtoupper(trim($stepRole));
        if ($roleUpper === '') {
            return collect();
        }

        $recipients = User::query()
            ->where('is_disabled', false)
            ->where(function ($q) use ($roleUpper) {
                $q->whereRaw('UPPER(role) = ?', [$roleUpper])
                    ->orWhereRaw('UPPER(unit_usaha) = ?', [$roleUpper]);
            })
            ->get();

        $cabangUpper = strtoupper(trim($cabang));
        if ($cabangUpper !== '' && str_starts_with($cabangUpper, $roleUpper . ' ')) {
            $recipients = $recipients->merge(
                User::query()
                    ->where('is_disabled', false)
                    ->whereRaw('UPPER(unit_usaha) = ?', [$cabangUpper])
                    ->get()
            );
        }

        if ($roleUpper === 'MANAJER AUDIT') {
            $recipients = $recipients->merge(
                User::query()->where('is_disabled', false)->where('role', 'manajer')->get()
            );
        }

        if ($recipients->isEmpty()) {
            $recipients = User::query()->where('is_disabled', false)->where('role', 'admin')->get();
        }

        return $recipients->unique('id')->values();
    }

    /** User(s) dengan salah satu role di $roles (dipakai untuk step SK: manajer/afd). */
    public static function recipientsForRoles(array $roles): Collection
    {
        $recipients = User::query()
            ->where('is_disabled', false)
            ->whereIn('role', $roles)
            ->get();

        if ($recipients->isEmpty()) {
            $recipients = User::query()->where('is_disabled', false)->where('role', 'admin')->get();
        }

        return $recipients->unique('id')->values();
    }
}
