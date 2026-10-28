<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:seo-migration-audit')]
#[Description('Compare the original WordPress post inventory with this database without changing records')]
class SeoMigrationAudit extends Command
{
    public function handle(): int
    {
        $source = json_decode(file_get_contents(resource_path('catalogue/wordpress-seo-source.json')), true, 32, JSON_THROW_ON_ERROR);
        $rows = [];
        $counts = ['published' => 0, 'draft' => 0, 'scheduled' => 0, 'missing' => 0];
        foreach ($source['posts'] as $entry) {
            $post = Post::query()->where('wordpress_id', $entry['id'])->first();
            $state = ! $post ? 'missing' : (! $post->is_published ? 'draft' : ($post->published_at?->isFuture() ? 'scheduled' : 'published'));
            $counts[$state]++;
            $rows[] = [$entry['id'], $entry['title'], $state, $post ? '/blog/'.$post->slug : 'No verified destination'];
        }
        $this->table(['WP ID', 'Source article', 'State', 'Current destination'], $rows);
        $this->info('Published '.$counts['published'].'; drafts '.$counts['draft'].'; scheduled '.$counts['scheduled'].'; missing '.$counts['missing'].'.');
        $this->line('No records changed. Recover an absent ID with portfolio:import-wordpress FILE --only-id=ID, then review the draft. Global indexing is '.(config('portfolio.indexable') ? 'enabled.' : 'disabled.'));

        return self::SUCCESS;
    }
}
