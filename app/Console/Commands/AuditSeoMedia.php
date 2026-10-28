<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Support\MediaSeo;
use App\Support\PublicSchema;
use App\Support\PublicSeo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:seo-media-audit {--all : Include drafts and sample content}')]
#[Description('Report image descriptions, unavailable local dimensions and incomplete video metadata without changing content')]
class AuditSeoMedia extends Command
{
    public function handle(): int
    {
        $rows = [];
        $profile = Profile::query()->where('key', 'main')->first();
        if ($profile && blank($profile->portrait_alt)) {
            $rows[] = [PublicSeo::url('about'), 'Portrait', 'Uses public name as fallback alt text; review the portrait description.'];
        }
        foreach (['blog' => Post::class, 'projects' => Project::class, 'services' => Service::class] as $section => $class) {
            $query = $class::query();
            if (! $this->option('all')) {
                $query->published();
                if ($class !== Service::class) {
                    $query->where('is_demo', false);
                }
            }
            foreach ($query->lazyById(100) as $entry) {
                $url = PublicSeo::url($section.'.show', ['slug' => $entry->slug]);
                if ($entry->cover_image) {
                    if (blank($entry->cover_image_alt)) {
                        $rows[] = [$url, 'Cover', 'Uses content title as fallback alt text; review the image description.'];
                    }
                    if (! isset(MediaSeo::image($entry->cover_image)['width'])) {
                        $rows[] = [$url, 'Cover', 'Local image file or dimensions unavailable.'];
                    }
                }
                foreach ($entry->gallery ?? [] as $position => $path) {
                    $details = collect($entry->gallery_metadata ?? [])->first(fn (mixed $item): bool => is_array($item) && ($item['src'] ?? null) === $path);
                    if (blank($details['alt'] ?? null)) {
                        $rows[] = [$url, 'Gallery '.($position + 1), 'Add a description of this specific screenshot.'];
                    }
                    if (! isset(MediaSeo::image($path)['width'])) {
                        $rows[] = [$url, 'Gallery '.($position + 1), 'Local image file or dimensions unavailable.'];
                    }
                }
                $html = MediaSeo::body($entry->body, $entry->body_image_metadata);
                if (str_contains($html, '<img')) {
                    ['root' => $root] = MediaSeo::document($html);
                    foreach ($root->getElementsByTagName('img') as $image) {
                        if (blank($image->getAttribute('alt')) && $image->getAttribute('role') !== 'presentation') {
                            $rows[] = [$url, 'Inline image', 'Missing description: '.mb_substr($image->getAttribute('src'), 0, 180)];
                        }
                    }
                }
                if ($entry instanceof Project && $entry->video_url && ! PublicSchema::video($entry, $url)) {
                    $rows[] = [$url, 'Video', 'Video schema is off or actual title, description, thumbnail or upload date needs review.'];
                }
            }
        }
        if ($rows) {
            $this->table(['Page', 'Media', 'Review'], $rows);
        }
        $this->info('Media review: '.count($rows).' items. No content or files changed. External URLs were not fetched.');

        return self::SUCCESS;
    }
}
