<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Services\AktaMenuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Mapping menu → roles yang boleh melihatnya.
     *
     * Prinsip least privilege (default deny):
     *   - admin    : semua menu
     *   - manajer  : operasional + report (tanpa manajemen user/sistem)
     *   - auditor  : menu kerja audit
     *   - viewer   : hanya read-only report
     */
    private array $roleMap = [
        'akta.dashboard'     => ['admin', 'manajer', 'auditor', 'viewer'],
        'akta.database'      => ['admin', 'manajer', 'auditor'],
        'akta.plan-audit'    => ['admin', 'manajer', 'auditor'],
        'akta.task'          => ['admin', 'manajer', 'auditor'],
        'akta.audit'         => ['admin', 'manajer', 'auditor'],
        'akta.audit-mandiri' => ['admin', 'manajer', 'auditor'],
        'akta.report-audit'  => ['admin', 'manajer', 'auditor', 'viewer'],
        'akta.rekomendasi'   => ['admin', 'manajer', 'auditor'],
        'akta.pica'          => ['admin', 'manajer', 'auditor', 'viewer'],
        'akta.bu-performance'=> ['admin', 'manajer', 'auditor', 'viewer'],
        'akta.sk'            => ['admin', 'manajer', 'auditor'],
        'akta.pulsa'         => ['admin', 'manajer'],
        'akta.mobil-dinas'   => ['admin', 'manajer', 'auditor', 'mrr'],
        'akta.grafik-beban-sk' => ['admin', 'manajer', 'auditor', 'koordinator', 'coo'],
        // Admin-only: manajemen sistem
        'akta.pengguna'      => ['admin'],
        'akta.monitoring'    => ['admin'],
        'akta.pengaturan'    => ['admin'],
        'akta.manajemen-menu'=> ['admin'],
    ];

    public function run(): void
    {
        $defaults = config('akta_menu.items', []);

        DB::transaction(function () use ($defaults) {
            foreach ($defaults as $index => $item) {
                $menu = Menu::updateOrCreate(
                    ['route_name' => $item['route']],
                    [
                        'label'     => $item['label'],
                        'code'      => $item['code'],
                        'path'      => $item['path'],
                        'icon'      => 'circle',
                        'order'     => $index + 1,
                        'is_active' => true,
                    ]
                );

                $roles = $this->roleMap[$item['route']] ?? AktaMenuService::ROLES;
                $menu->syncRoles($roles);
            }
        });

        $this->command->info('Menu seeded: ' . count($defaults) . ' items dengan role assignment.');
    }
}
