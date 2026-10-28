<?php

namespace App\Console\Commands;

use App\Models\WordPressImport;
use App\Models\WordPressImportItem;
use App\Support\WordPressImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('portfolio:repair-wordpress-images {import? : Import ID; latest import by default} {--report : Show image counts and errors without queuing work}')]
#[Description('Rescan and repair images in existing WordPress imports while preserving edited posts')]
class RepairWordPressImages extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WordPressImporter $importer): int
    {
        $id = $this->argument('import');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('Use a positive import ID.');

            return self::FAILURE;
        }
        $import = $id === null ? WordPressImport::query()->latest('id')->first() : WordPressImport::query()->find($id);
        if (! $import) {
            $this->error('No matching WordPress import was found.');

            return self::FAILURE;
        }
        if (! $this->option('report')) {
            $result = $importer->repairImages($import);
            $this->info('Queued image repair for '.$result['queued'].' posts in import #'.$import->id.'.');
            if ($result['busy']) {
                $this->warn($result['busy'].' busy posts were skipped. Run the repair command again after the worker finishes.');
            }
            $this->line('Run the default queue worker, then rerun this command with --report to check image downloads.');
        }
        $counts = ['Image references' => 0, 'Downloaded' => 0, 'Failed' => 0, 'Pending' => 0, 'Missing local files' => 0];
        $examples = [];
        $import->items()->each(function (WordPressImportItem $item) use (&$counts, &$examples): void {
            foreach ($item->payload['media'] as $image) {
                $counts['Image references']++;
                if ($image['path']) {
                    $counts[Storage::disk('public')->exists($image['path']) ? 'Downloaded' : 'Missing local files']++;
                } elseif ($image['error']) {
                    $counts['Failed']++;
                    if (count($examples) < 8) {
                        $parts = parse_url($image['url']);
                        $examples[] = ($parts['host'] ?? '').($parts['path'] ?? '').' — '.$image['error'];
                    }
                } else {
                    $counts['Pending']++;
                }
            }
        });
        $this->table(['Import #'.$import->id.' images (references)', 'Count'], collect($counts)->map(fn (int $count, string $label): array => [$label, $count])->values()->all());
        foreach ($examples as $example) {
            $this->warn($example);
        }

        return self::SUCCESS;
    }
}
