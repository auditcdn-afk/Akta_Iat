<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AktaUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'display_name' => 'Administrator',
                'email' => 'admin@akta.local',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'unit_usaha' => 'HO',
                'is_disabled' => false,
                'created_by' => 'system',
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'auditor'],
            [
                'name' => 'Auditor AKTA',
                'display_name' => 'Auditor AKTA',
                'email' => 'auditor@akta.local',
                'password' => Hash::make('auditor12345'),
                'role' => 'auditor',
                'unit_usaha' => 'AUDIT',
                'is_disabled' => false,
                'created_by' => 'system',
            ]
        );
    }
}
