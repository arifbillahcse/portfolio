<?php

namespace App\Console\Commands;

use App\Support\SeoContentAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('portfolio:seo-content-audit {--all : Include drafts, scheduled and sample content} {--save : Save a private snapshot for the admin Content audit page} {--json : Print the report as JSON}')]
#[Description('Review saved content, metadata and contextual internal links without changing or publishing content')]
class AuditSeoContent extends Command
{
    public function handle(SeoContentAudit $audit): int
    {
        $lock = Cache::lock('seo-content-audit', 3600);
        if (! $lock->get()) {
            $this->error('A content audit is already running. Try again when it finishes.');

            return self::FAILURE;
        }
        try {
            $report = $audit->run((bool) $this->option('all'));
            $saved = $this->option('save') ? $audit->save($report) : null;
            if ($this->option('json')) {
                $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } else {
                $this->table(['Page', 'State', 'Words', 'Review items'], array_map(fn (array $row): array => [
                    $row['url'], $row['state'], $row['scope'] === 'body' ? $row['stats']['words'] : 'Metadata only', $row['review_count'],
                ], $report['entries']));
                $this->info('Reviewed '.$report['totals']['pages'].' pages; '.$report['totals']['with_reviews'].' have review items. No content or publication settings changed.');
                $this->line($report['method']);
                $this->line('Global indexing is '.($report['indexing_enabled'] ? 'enabled.' : 'disabled.'));
                if ($saved) {
                    $this->line('Saved private report: '.$saved);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Content audit failed. No content was changed. Check storage/logs/laravel.log for details.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
