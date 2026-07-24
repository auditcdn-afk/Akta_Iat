<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Baris tabel menu_roles sebagai model Eloquent nyata (bukan DB::table() query
// mentah) supaya Menu::roles() bisa di-eager-load — lihat Menu::getAllowedRoles()
// dan AktaMenuService (perbaikan N+1: dulu 1-2 query DB::table() per menu row
// tiap kali sidebar/daftar menu admin dirender).
class MenuRole extends Model
{
    protected $table = 'menu_roles';

    protected $fillable = ['menu_id', 'role'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
