<?php

namespace App\Console\Commands;

use App\Services\PlanTaskService;
use Illuminate\Console\Command;

class SyncPlanTasks extends Command
{
    /**
     * Contoh:
     *   php artisan akta:sync-plan-tasks
     */
    protected $signature = 'akta:sync-plan-tasks';

    protected $description = 'Buat task auditor secara otomatis untuk seluruh Plan Audit yang belum punya task (backfill plan lama).';

    public function handle(PlanTaskService $service): int
    {
        $this->info('Menyinkronkan Plan Audit → Task auditor...');

        $created = $service->syncAll('artisan');

        $this->info("Selesai. {$created} task baru dibuat.");

        return self::SUCCESS;
    }
}
