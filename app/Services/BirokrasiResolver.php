<?php

namespace App\Services;

use App\Models\PlanAudit;
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
     * Bolehkah user ini mengisi step $stepRole pada rekomendasi cabang $cabang?
     *
     * SATU-SATUNYA definisi "pemilik step" di aplikasi. Dipakai untuk
     * OTORISASI di AuditRecommendationController::approveStep(), untuk
     * menentukan tombol "Isi Keputusan" mana yang muncul di layar, dan untuk
     * memilih penerima notifikasi (lihat recipientsForStep di bawah).
     *
     * Dulu otorisasinya punya aturan sendiri: admin/manajer/auditor boleh
     * mengisi step APA PUN kecuali AFD. Akibatnya auditor bisa menuliskan
     * keputusan atas nama FIN REG, REG HEAD, atau unit usaha -- padahal seluruh
     * gunanya Keputusan Bertahap adalah tiap pihak menuliskan keputusannya
     * sendiri. Sekarang hanya ADMIN yang boleh menimpa, sebagai jalur darurat.
     *
     * Aturannya sama dengan yang tertulis di config/birokrasi.php: nilai
     * approver dicocokkan ke role user ATAU unit_usaha user, huruf besar-kecil
     * diabaikan. Ditambah dua hal:
     *
     *   - Step generik jenis unit usaha ("SO", "CSC", "WHS"): nama unit usaha
     *     diawali kata jenisnya ("SO ALB", "WHS Unit ARK"), jadi unit usaha
     *     pemilik plan boleh mengisi step jenisnya sendiri.
     *   - Step "Manajer Audit" / "Manajer IAT DEPT": label tampilannya berbeda
     *     dari slug role-nya ("manajer"), jadi dicocokkan khusus.
     */
    public static function bolehMengisiStep(?User $user, ?string $stepRole, ?string $cabang): bool
    {
        $step = strtoupper(trim((string) $stepRole));
        if (!$user || $step === '') {
            return false;
        }

        $role = strtoupper(trim((string) $user->role));
        $unit = strtoupper(trim((string) $user->unit_usaha));

        if ($role === $step || ($unit !== '' && $unit === $step)) {
            return true;
        }

        if ($role === 'MANAJER' && in_array($step, self::STEP_MANAJER, true)) {
            return true;
        }

        // Step generik jenis unit usaha.
        $cabangUpper = strtoupper(trim((string) $cabang));

        return $cabangUpper !== ''
            && $unit === $cabangUpper
            && str_starts_with($cabangUpper, $step . ' ');
    }

    /** Label step yang sebenarnya dijalankan role "manajer" (slug-nya beda dari labelnya). */
    private const STEP_MANAJER = ['MANAJER AUDIT', 'MANAJER IAT DEPT'];

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

        // Disaring dengan predikat yang sama persis dengan yang dipakai
        // otorisasi, supaya "siapa yang diberi tahu" dan "siapa yang boleh
        // mengisi" tidak pernah berbeda. Tabel user berukuran puluhan baris,
        // jadi menyaringnya di PHP tidak jadi soal.
        $recipients = User::query()
            ->where('is_disabled', false)
            ->get()
            ->filter(fn (User $u) => self::bolehMengisiStep($u, $roleUpper, $cabang))
            ->values();

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

    /**
     * Tim plan itu sendiri — kepala tim, anggota tim, dan pembuatnya.
     *
     * Berbeda dari recipientsForPlanStatus(['auditor'], ...) yang menyapa
     * SELURUH auditor: kabar penolakan hanya berguna bagi orang yang memang
     * harus memperbaiki plan tersebut. Nama tim disimpan sebagai teks, jadi
     * dicocokkan ke display_name / name / username dengan huruf besar-kecil
     * dan spasi berlebih diabaikan (lihat PlanAudit::dimilikiOleh).
     *
     * Kalau tidak satu pun nama cocok dengan akun yang ada, jatuh kembali ke
     * seluruh auditor — lebih baik terlalu banyak yang tahu daripada plan yang
     * ditolak menggantung tanpa ada yang diberi tahu.
     */
    public static function recipientsForPlanTeam(PlanAudit $plan): Collection
    {
        $rapikan = fn ($nama) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $nama)));

        $nama = collect([$plan->kepala_tim, $plan->created_by])
            ->merge($plan->tim ?: [])
            ->map($rapikan)
            ->filter(fn ($n) => $n !== '')
            ->unique();

        if ($nama->isEmpty()) {
            return self::recipientsForPlanStatus(['auditor'], $plan->cabang);
        }

        $recipients = User::query()
            ->where('is_disabled', false)
            ->get()
            ->filter(fn (User $u) => collect([$u->display_name, $u->name, $u->username])
                ->map($rapikan)
                ->filter(fn ($n) => $n !== '')
                ->intersect($nama)
                ->isNotEmpty());

        return $recipients->isEmpty()
            ? self::recipientsForPlanStatus(['auditor'], $plan->cabang)
            : $recipients->unique('id')->values();
    }

    /**
     * Akun pengaju sebuah dokumen, dicari dari kolom created_by (username, dan
     * pada data lama bisa berupa email). Kosong kalau akunnya sudah tidak ada.
     */
    public static function recipientsForPengaju(?string $createdBy): Collection
    {
        $nama = trim((string) $createdBy);

        if ($nama === '') {
            return collect();
        }

        return User::query()
            ->where('is_disabled', false)
            ->where(function ($q) use ($nama) {
                $q->where('username', $nama)->orWhere('email', $nama);
            })
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * User(s) yang berwenang memajukan status Plan Audit dari status
     * sekarang (dipakai untuk notifikasi "giliran memproses plan").
     * $roles memakai nilai yang sama seperti PlanAuditController::TRANSITIONS
     * (role slug, atau "__branch__" untuk akun cabang pemilik plan). Role
     * "admin" sengaja tidak dianggap tujuan notifikasi -- itu jalur override,
     * bukan pemilik gerbang -- kecuali sebagai fallback saat tidak ada
     * penerima lain sama sekali.
     */
    public static function recipientsForPlanStatus(array $roles, ?string $cabang): Collection
    {
        $roleNames = array_values(array_diff($roles, ['__branch__', 'admin']));

        $recipients = $roleNames
            ? User::query()->where('is_disabled', false)->whereIn('role', $roleNames)->get()
            : collect();

        if (in_array('__branch__', $roles, true) && $cabang) {
            $recipients = $recipients->merge(
                User::query()
                    ->where('is_disabled', false)
                    ->whereRaw('UPPER(unit_usaha) = ?', [strtoupper($cabang)])
                    ->get()
            );
        }

        if ($recipients->isEmpty()) {
            $recipients = User::query()->where('is_disabled', false)->where('role', 'admin')->get();
        }

        return $recipients->unique('id')->values();
    }
}
