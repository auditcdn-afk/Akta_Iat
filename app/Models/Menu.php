<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = [
        'label', 'code', 'route_name', 'path', 'icon', 'parent_id', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'order'      => 'integer',
        'parent_id'  => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    // ── Scopes ───────────────────────────────────────────────────

    /** Filter menu yang boleh dilihat oleh role tertentu (server-side). */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->whereExists(function ($sub) use ($role) {
            $sub->select(DB::raw(1))
                ->from('menu_roles')
                ->whereColumn('menu_roles.menu_id', 'menus.id')
                ->where('menu_roles.role', $role);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRootLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // ── Role helpers ─────────────────────────────────────────────

    /** Daftar semua role yang boleh mengakses menu ini. */
    public function getAllowedRoles(): array
    {
        return DB::table('menu_roles')
            ->where('menu_id', $this->id)
            ->pluck('role')
            ->toArray();
    }

    /**
     * Ganti role untuk menu ini (delete-insert idempotent).
     * Security: hanya dipanggil dari controller yang sudah diproteksi admin.
     */
    public function syncRoles(array $roles): void
    {
        DB::table('menu_roles')->where('menu_id', $this->id)->delete();

        $now = now();
        foreach (array_unique($roles) as $role) {
            DB::table('menu_roles')->insert([
                'menu_id'    => $this->id,
                'role'       => $role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ── Serialization ─────────────────────────────────────────────

    public function toAktaArray(): array
    {
        return [
            'id'        => $this->id,
            'label'     => $this->label,
            'code'      => $this->code,
            'routeName' => $this->route_name,
            'path'      => $this->path,
            'icon'      => $this->icon,
            'parentId'  => $this->parent_id,
            'order'     => $this->order,
            'isActive'  => $this->is_active,
            'roles'     => $this->getAllowedRoles(),
        ];
    }
}
