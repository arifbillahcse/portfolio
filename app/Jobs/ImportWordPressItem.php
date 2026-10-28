<?php

namespace App\Jobs;

use App\Models\WordPressImportItem;
use App\Support\WordPressImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ImportWordPressItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 25;

    public function __construct(public int $itemId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('wordpress-item-'.$this->itemId))->releaseAfter(10)->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(WordPressImporter $importer): void
    {
        $item = WordPressImportItem::query()->find($this->itemId);
        if ($item) {
            $importer->process($item);
        }
    }

    public function failed(?Throwable $exception): void
    {
        WordPressImportItem::query()->whereKey($this->itemId)->whereNotIn('status', ['imported', 'warnings', 'skipped'])
            ->update(['status' => 'failed', 'error' => 'Import job stopped. Use Resume / retry to continue. Existing posts are preserved.']);
    }
}
