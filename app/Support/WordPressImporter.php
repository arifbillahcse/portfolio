<?php

namespace App\Support;

use App\Jobs\ImportWordPressItem;
use App\Models\Category;
use App\Models\Post;
use App\Models\WordPressImport;
use App\Models\WordPressImportItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WordPressImporter
{
    public function __construct(private WordPressXml $xml, private WordPressMedia $media, private WordPressMarkdown $markdown) {}

    public function start(string $path, string $filename): WordPressImport
    {
        $posts = $this->xml->read($path);
        $import = DB::transaction(function () use ($posts, $filename): WordPressImport {
            $import = WordPressImport::query()->create(['filename' => mb_substr(basename($filename), 0, 255), 'total' => count($posts)]);
            foreach ($posts as $post) {
                $import->items()->create(['title' => $post['title'], 'payload' => $post]);
            }

            return $import;
        });
        $import->items()->select('id')->each(fn (WordPressImportItem $item) => $this->dispatch($item->id));

        return $import;
    }

    public function dispatch(int $itemId): void
    {
        ImportWordPressItem::dispatch($itemId)->onConnection('database')->onQueue('default')->afterCommit();
    }

    public function resume(WordPressImport $import): void
    {
        $import->items()->whereNotIn('status', ['imported', 'skipped'])->each(function (WordPressImportItem $item): void {
            $payload = $item->payload;
            foreach ($payload['media'] as &$image) {
                if ($image['error']) {
                    $image['attempted'] = false;
                    $image['error'] = null;
                }
            }
            unset($image);
            $item->update(['status' => 'queued', 'error' => null, 'payload' => $payload]);
            $this->dispatch($item->id);
        });
    }

    /** Process at most one image per job to fit shared-hosting worker limits. */
    public function process(WordPressImportItem $item): void
    {
        if (in_array($item->status, ['imported', 'warnings', 'skipped'], true)) {
            return;
        }
        $payload = $item->payload;
        $existing = Post::query()->where('wordpress_id', $payload['wordpress_id'])->first();
        if ($existing && $existing->id !== $item->post_id) {
            $item->update(['status' => 'skipped', 'error' => 'This WordPress post was already imported. Existing edits were preserved.']);

            return;
        }
        foreach ($payload['media'] as $index => $image) {
            if ($image['attempted']) {
                continue;
            }
            try {
                $payload['media'][$index]['path'] = $this->media->download($image['url']);
            } catch (Throwable $exception) {
                $payload['media'][$index]['error'] = $exception instanceof \RuntimeException ? Str::limit($exception->getMessage(), 250) : 'Download failed. Check WordPress availability and retry.';
            }
            $payload['media'][$index]['attempted'] = true;
            $item->update(['status' => 'processing', 'payload' => $payload]);
            $this->dispatch($item->id);

            return;
        }
        $images = [];
        $errors = [];
        foreach ($payload['media'] as $image) {
            if ($image['path']) {
                $images[$image['url']] = $image['path'];
            }
            if ($image['error']) {
                $errors[] = $image['url'].' — '.$image['error'];
            }
        }
        DB::transaction(function () use ($item, $payload, $existing, $images, $errors): void {
            $post = $existing;
            if (! $post) {
                $slug = mb_substr(trim($payload['slug']), 0, 220);
                if ($slug === '' || preg_match('/[\s\/\\\\?#\x00-\x1F]/u', $slug)) {
                    $slug = Str::slug($payload['title']) ?: 'wordpress-'.$payload['wordpress_id'];
                }
                $baseSlug = $slug;
                $suffix = 0;
                while (Post::query()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-wp-'.$payload['wordpress_id'].($suffix ? '-'.$suffix : '');
                    $suffix++;
                }
                $category = $payload['category'];
                $categoryRecord = $category ? Category::query()->firstOrCreate(
                    ['type' => 'post', 'slug' => mb_substr(Str::slug($category['slug'] ?: $category['name']) ?: 'wordpress', 0, 255)],
                    ['name' => mb_substr($category['name'], 0, 255)],
                ) : null;
                try {
                    $date = $payload['date'] && ! str_starts_with($payload['date'], '0000') ? CarbonImmutable::parse($payload['date'], 'UTC') : null;
                    if ($date && ($date->year < 1971 || $date->year > 2100)) {
                        $date = null;
                    }
                } catch (Throwable) {
                    $date = null;
                }
                $path = parse_url($payload['link'], PHP_URL_PATH);
                $body = $this->markdown->convert($payload['html'], $images);
                $post = Post::query()->create([
                    'wordpress_id' => $payload['wordpress_id'],
                    'wordpress_path' => is_string($path) && strlen($path) <= 512 && $path !== '/' ? '/'.trim(rawurldecode($path), '/') : null,
                    'title' => $payload['title'], 'slug' => $slug, 'body' => $body,
                    'excerpt' => Str::limit(trim(strip_tags($payload['excerpt'] ?: $payload['html'])), 300, ''),
                    'cover_image' => $images[$payload['cover']] ?? null,
                    'category_id' => $categoryRecord?->id, 'is_published' => false, 'is_demo' => false,
                    'published_at' => $date?->setTimezone(config('app.timezone')), 'reading_minutes' => min(120, max(1, (int) ceil(str_word_count(strip_tags($payload['html'])) / 200))),
                ]);
            } else {
                $body = $post->body ?? '';
                foreach ($images as $original => $local) {
                    $body = str_replace($original, Storage::disk('public')->url($local), $body);
                }
                $post->update(['body' => $body, 'cover_image' => $post->cover_image ?: ($images[$payload['cover']] ?? null)]);
            }
            $item->update(['post_id' => $post->id, 'status' => $errors ? 'warnings' : 'imported', 'error' => $errors ? implode("\n", $errors) : null]);
        });
    }
}
