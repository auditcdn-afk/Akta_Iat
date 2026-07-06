<?php

namespace App\Services;

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
}
