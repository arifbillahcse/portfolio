<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

#[Signature('portfolio:assets {--source= : Directory containing the original portfolio assets}')]
#[Description('Publish the portfolio stylesheet, script, and portrait into the Laravel public directory')]
class SyncFrontendAssets extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->option('source') ?: dirname(base_path());
        $assets = [
            'styles.css' => 'assets/css/styles.css',
            'script.js' => 'assets/js/script.js',
            'arif-formal.png' => 'assets/images/arif-formal.png',
        ];

        foreach ($assets as $filename => $destination) {
            if (! File::isFile($source.DIRECTORY_SEPARATOR.$filename)) {
                $this->error('Missing source asset: '.$filename);

                return self::FAILURE;
            }
        }

        foreach ($assets as $filename => $destination) {
            $target = public_path($destination);
            File::ensureDirectoryExists(dirname($target));

            if (! File::copy($source.DIRECTORY_SEPARATOR.$filename, $target)) {
                throw new RuntimeException('Could not publish asset: '.$filename);
            }
        }

        $this->info('Published portfolio CSS, JavaScript, and portrait.');

        return self::SUCCESS;
    }
}
