<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',   'label' => 'Administrator', 'color' => 'red',   'description' => 'Akses penuh ke semua fitur dan pengaturan sistem.',      'is_system' => true,  'order' => 1],
            ['name' => 'manajer', 'label' => 'Manajer',       'color' => 'amber', 'description' => 'Kelola plan audit, task, approval, dan laporan.',         'is_system' => true,  'order' => 2],
            ['name' => 'auditor', 'label' => 'Auditor',       'color' => 'blue',  'description' => 'Input dan kelola data audit, task, dan rekomendasi.',     'is_system' => true,  'order' => 3],
            ['name' => 'viewer',  'label' => 'Viewer',         'color' => 'slate', 'description' => 'Hanya bisa melihat laporan dan data read-only.',          'is_system' => false, 'order' => 4],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('Roles seeded: ' . count($roles) . ' roles.');
    }
}
