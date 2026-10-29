<?php

namespace App\Console\Commands;

use App\Support\ResponsiveImages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('portfolio:optimize-media {--path=* : Only these relative public image paths} {--limit=50 : Maximum pending images processed per run} {--force : Retry unchanged sources or rebuild missing copies}')]
#[Description('Prepare smaller responsive WebP copies offline while retaining all originals')]
class OptimizePortfolioMedia extends Command
{
    public function handle(ResponsiveImages $images): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if (! $limit) {
            $this->error('Choose a limit between 1 and 1000.');

            return self::FAILURE;
        }
        if (! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            $this->error('Enable PHP GD with WebP support, then retry. The website continues using the originals.');

            return self::FAILURE;
        }
        $lock = Cache::lock('portfolio-optimize-media', 3600);
        if (! $lock->get()) {
            $this->error('Media optimization is already running.');

            return self::FAILURE;
        }
        try {
            $paths = $this->option('path');
            if (! $paths) {
                $paths = ['assets/images/arif-formal.png'];
                if (config('filesystems.disks.public.driver') === 'local') {
                    foreach (['posts', 'projects', 'profile'] as $directory) {
                        $root = Storage::disk('public')->path($directory);
                        if (! is_dir($root)) {
                            continue;
                        }
                        foreach (File::allFiles($root) as $file) {
                            $relative = $directory.'/'.str_replace('\\', '/', $file->getRelativePathname());
                            if (! str_contains($relative, '/responsive/') && preg_match('/\.(png|jpe?g|webp)$/i', $relative)) {
                                $paths[] = $relative;
                            }
                        }
                    }
                }
            }
            $manifest = $images->manifest();
            $manifest['version'] = 1;
            $manifest['images'] ??= [];
            $processed = 0;
            $unchanged = 0;
            $failed = 0;
            $pending = 0;
            foreach (array_unique($paths) as $path) {
                $source = $images->source($path);
                if (! $source) {
                    $this->warn('Unsupported or missing source: '.$path);
                    $failed++;

                    continue;
                }
                if (! $this->option('force') && $images->fresh($manifest['images'][$path] ?? null, $source['absolute'])) {
                    $unchanged++;

                    continue;
                }
                if ($processed >= $limit) {
                    $pending++;

                    continue;
                }
                $manifest['images'][$path] = $images->build($path);
                $entry = $manifest['images'][$path];
                $processed++;
                $this->line($path.': '.count($entry['variants']).' smaller WebP copies'.(isset($entry['skipped']) ? ' — '.$entry['skipped'] : '.'));
            }
            $images->save($manifest);
            $this->info('Processed '.$processed.'; unchanged '.$unchanged.'; pending '.$pending.'; unsupported/missing '.$failed.'. Originals were retained.');
            if ($pending) {
                $this->line('Run the same command again to process the remaining pending sources.');
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Optimization stopped. Originals remain available. Check storage/logs/laravel.log.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
