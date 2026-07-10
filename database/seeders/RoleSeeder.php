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
            ['name' => 'manajer', 'label' => 'Manajer Audit',  'color' => 'amber', 'description' => 'Kelola plan audit, task, approval, dan laporan.',         'is_system' => true,  'order' => 2],
            ['name' => 'auditor', 'label' => 'Auditor',       'color' => 'blue',  'description' => 'Input dan kelola data audit, task, dan rekomendasi.',     'is_system' => true,  'order' => 3],
            ['name' => 'koordinator', 'label' => 'Koordinator',    'color' => 'green',  'description' => 'Koordinator audit, menyetujui pinjaman tahap pertama.',  'is_system' => false, 'order' => 4],
            ['name' => 'coo',         'label' => 'COO',            'color' => 'orange', 'description' => 'Chief Operating Officer, menyetujui pinjaman BPK.',        'is_system' => false, 'order' => 5],
            ['name' => 'h1',          'label' => 'Unit Usaha H1',  'color' => 'teal',   'description' => 'Kepala Unit Usaha H1, tujuan pinjaman cabang BPK.',         'is_system' => false, 'order' => 6],
            ['name' => 'h2',          'label' => 'Unit Usaha H2',  'color' => 'cyan',   'description' => 'Kepala Unit Usaha H2.',                                     'is_system' => false, 'order' => 7],
            ['name' => 'bpk',         'label' => 'Role BPK',       'color' => 'purple', 'description' => 'Pemegang peran akhir persetujuan pinjaman BPK/BPB.',        'is_system' => false, 'order' => 8],
            ['name' => 'unit',        'label' => 'Unit Usaha',     'color' => 'sky',    'description' => 'Perwakilan unit usaha (SO/CSC/WHS).',                       'is_system' => false, 'order' => 9],
            ['name' => 'viewer',      'label' => 'Viewer',         'color' => 'slate',  'description' => 'Hanya bisa melihat laporan dan data read-only.',            'is_system' => false, 'order' => 10],
            ['name' => 'mrr',         'label' => 'MRR',            'color' => 'indigo', 'description' => 'Menerima form pengajuan mobil dinas dan melengkapi data supir/kendaraan.', 'is_system' => false, 'order' => 11],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('Roles seeded: ' . count($roles) . ' roles.');
    }
}
