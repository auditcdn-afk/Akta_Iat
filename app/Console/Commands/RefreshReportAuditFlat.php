<?php

namespace App\Console\Commands;

use App\Services\ReportAuditFlattener;
use Illuminate\Console\Command;

class RefreshReportAuditFlat extends Command
{
    /**
     * Contoh:
     *   php artisan akta:refresh-report-audit-flat
     */
    protected $signature = 'akta:refresh-report-audit-flat';

    protected $description = 'Bangun ulang report_audit_summaries + report_audit_checklist_items dari data plan audit, realisasi dinas, grading, SK, rekomendasi, dan PICA — sumber data untuk Power BI & export Excel.';

    public function handle(ReportAuditFlattener $flattener): int
    {
        $this->info('Menyusun ulang data ringkasan report audit...');

        $count = $flattener->refreshAll();

        $this->info("Selesai. {$count} plan audit diproses.");

        return self::SUCCESS;
    }
}
