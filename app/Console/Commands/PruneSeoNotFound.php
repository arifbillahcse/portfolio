<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('portfolio:prune-seo-404')]
#[Description('Remove sampled 404 logs not seen for 30 days')]
class PruneSeoNotFound extends Command
{
    public function handle(): int
    {
        $count = DB::table('seo_not_found')->where('last_seen_at', '<', now()->subDays(30))->delete();
        $this->info('Pruned '.$count.' expired 404 log entries.');

        return self::SUCCESS;
    }
}
