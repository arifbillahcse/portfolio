<?php

namespace App\Console\Commands;

use App\Support\GrowthReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:sync-growth-report {--days=28 : Reporting window in days (7-90)}')]
#[Description('Refresh the private growth report from Search Console, GA4 and saved inquiries')]
class SyncGrowthReport extends Command
{
    public function handle(GrowthReport $growth): int
    {
        $report = $growth->build((int) $this->option('days'));
        $growth->save($report);

        $this->info('Saved growth report: '.$report['leads']['total'].' leads in the last '.$report['days'].' days.');
        foreach ($report['errors'] as $source => $message) {
            $this->warn($source.': '.$message);
        }

        return self::SUCCESS;
    }
}
