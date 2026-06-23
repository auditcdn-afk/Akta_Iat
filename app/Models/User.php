<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'username',
        'name',
        'display_name',
        'email',
        'photo_path',
        'password',
        'plain_password',
        'role',
        'unit_usaha',
        'wilayah',
        'is_disabled',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'plain_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_disabled' => 'boolean',
        ];
    }

    public function getDisplayNameAttribute($value): string
    {
        return $value ?: $this->name ?: $this->username ?: 'User';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function toAktaArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'displayName' => $this->display_name,
            'name' => $this->name,
            'email' => $this->email,
            'photoUrl' => $this->photo_url,
            'role' => $this->role,
            'unitUsaha' => $this->unit_usaha ?: '',
            'wilayah' => $this->wilayah ?: '',
            'isDisabled' => (bool) $this->is_disabled,
        ];
    }
}
