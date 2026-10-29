<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SeoRedirect;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;

class SeoContentAudit
{
    public const REPORT_PATH = 'seo/content-audit.json';

    /** @return array<string, mixed> */
    public function run(bool $includeDrafts = false): array
    {
        $site = SiteSetting::query()->where('key', 'main')->first() ?? new SiteSetting(['brand_name' => 'Arif Portfolio']);
        $profile = Profile::query()->where('key', 'main')->first() ?? new Profile(['name' => 'Arif']);
        $inventory = [];
        $aliases = ['/index.html' => '/'];
        $legacyPaths = [];
        $rows = [];
        foreach (SeoMetadata::PAGES as $page => $label) {
            $path = $page === 'home' ? '/' : '/'.$page;
            $inventory[$path] = ['key' => 'pages:'.$page, 'kind' => 'pages', 'id' => $page, 'url' => PublicSeo::url($page),
                'state' => 'published', 'public' => true, 'eligible' => SeoMetadata::sitemapAllowed($site->page_seo[$page] ?? [], PublicSeo::url($page))];
            $aliases['/'.$page.'.html'] = $path;
        }
        $aliases['/?page_id=3730'] = '/about';
        $aliases['/?page_id=3761'] = '/services';
        $models = ['blog' => Post::class, 'projects' => Project::class, 'services' => Service::class];
        foreach ($models as $kind => $class) {
            $fields = ['id', 'slug', 'is_published', 'published_at', 'seo_robots', 'seo_canonical_url'];
            if ($kind !== 'services') {
                $fields[] = 'is_demo';
            }
            if ($kind === 'blog') {
                $fields = [...$fields, 'wordpress_id', 'wordpress_path'];
            }
            foreach ($class::query()->select($fields)->lazyById(200) as $record) {
                $public = $record->is_published && (! $record->published_at || ! $record->published_at->isFuture());
                $state = $record->is_demo ? 'sample' : (! $record->is_published ? 'draft' : ($public ? 'published' : 'scheduled'));
                $url = PublicSeo::url($kind.'.show', ['slug' => $record->slug]);
                $path = '/'.$kind.'/'.$record->slug;
                $inventory[$path] = ['key' => $kind.':'.$record->id, 'kind' => $kind, 'id' => $record->id, 'url' => $url,
                    'state' => $state, 'public' => (bool) $public,
                    'eligible' => $public && ! $record->is_demo && SeoMetadata::sitemapAllowed($record->getAttributes(), $url)];
                if ($kind === 'blog' && $public) {
                    if ($record->wordpress_id) {
                        $aliases['/?p='.$record->wordpress_id] = $path;
                    }
                    if ($record->wordpress_path && $record->wordpress_path !== '/' && ! isset($legacyPaths[$record->wordpress_path])) {
                        $legacyPaths[$record->wordpress_path] = $path;
                    }
                }
            }
        }
        foreach ($legacyPaths as $source => $destination) {
            if (! isset($inventory[$source])) {
                $aliases[$source] ??= $destination;
            }
        }
        foreach (['blog', 'projects'] as $kind) {
            if (! collect($inventory)->contains(fn (array $entry): bool => $entry['kind'] === $kind && $entry['state'] === 'published')) {
                $inventory['/'.$kind]['eligible'] = false;
            }
        }
        foreach (SeoRedirect::query()->where('enabled', true)->cursor() as $rule) {
            $aliases[$rule->source_path] = $rule->destination_path;
        }
        foreach (SeoMetadata::PAGES as $page => $label) {
            $item = $inventory[$page === 'home' ? '/' : '/'.$page];
            $attributes = $site->page_seo[$page] ?? [];
            $defaults = SeoMetadata::pageDefaults($page, $profile, $site);
            $metadata = SeoMetadata::resolve($attributes, $site, $profile, $page === 'home' ? 'home' : 'pages',
                $defaults['title'], $defaults['description'], $item['url']);
            $rows[$item['key']] = $this->row($item, $attributes, $metadata, false);
        }
        foreach ($models as $kind => $class) {
            $query = $class::query();
            if (! $includeDrafts) {
                $query->published();
                if ($kind !== 'services') {
                    $query->where('is_demo', false);
                }
            }
            foreach ($query->lazyById(100) as $record) {
                $item = $inventory['/'.$kind.'/'.$record->slug];
                $attributes = $record->getAttributes();
                $attributes['body_image_metadata'] = $record->body_image_metadata;
                $attributes['seo_related_keywords'] = $record->seo_related_keywords;
                $attributes['summary'] = $record->excerpt ?? $record->summary ?? '';
                $metadata = SeoMetadata::resolve($attributes, $site, $profile, $kind, $record->title, $attributes['summary'], $item['url']);
                $rows[$item['key']] = $this->row($item, $attributes, $metadata, true);
            }
        }
        $incoming = [];
        foreach ($rows as $key => &$row) {
            $broken = [];
            $redirected = [];
            $restricted = [];
            foreach ($row['links'] as $link) {
                $path = $link['path'];
                if (preg_match('~^/(?:storage|assets|build)/|\\.(?:pdf|zip|png|jpe?g|gif|webp|svg|txt)$~i', $path)) {
                    continue;
                }
                $target = $this->resolve($path, $inventory, $aliases);
                if (! $target) {
                    $broken[] = $path;

                    continue;
                }
                if ($target['redirected']) {
                    $redirected[] = $path.' → '.$inventory[$target['path']]['url'];
                }
                $targetItem = $inventory[$target['path']];
                if (! $targetItem['eligible']) {
                    $restricted[] = $path;
                }
                if ($row['eligible'] && $targetItem['key'] !== $key) {
                    $incoming[$targetItem['key']][$key] = true;
                }
            }
            foreach (['unavailable_links' => ['Internal destination is not a published portfolio page in this database', $broken],
                'redirected_links' => ['Link to the current destination directly', $redirected],
                'restricted_links' => ['Internal destination is noindex, sample content or canonicalized elsewhere; review intent', $restricted]] as $code => [$message, $paths]) {
                if ($paths) {
                    $this->add($row, $code, $message.': '.implode('; ', array_slice(array_unique($paths), 0, 8)).'. Downloads, external URLs and fragments are not availability-checked.');
                }
            }
        }
        unset($row);
        foreach (['search_title' => 'effective search title', 'search_description' => 'effective description', 'topic' => 'focus topic', 'fingerprint' => 'normalized explanatory body'] as $field => $label) {
            $groups = [];
            foreach ($rows as $key => $row) {
                $value = $field === 'fingerprint' ? $row[$field] : ContentAnalysis::normalize($row[$field]);
                if ($row['eligible'] && filled($value)) {
                    $groups[$value][] = $key;
                }
            }
            foreach ($groups as $keys) {
                if (count($keys) < 2) {
                    continue;
                }
                foreach ($keys as $key) {
                    $others = array_values(array_diff($keys, [$key]));
                    $names = array_map(fn (string $other): string => $rows[$other]['url'], array_slice($others, 0, 4));
                    $this->add($rows[$key], 'duplicate_'.$field, 'Same '.$label.' also used by '.count($others).' other page(s): '.implode(', ', $names).'. Review intent and originality; this is not proof of a penalty or keyword cannibalization.');
                }
            }
        }
        foreach ($rows as $key => &$row) {
            $row['incoming_contextual'] = count($incoming[$key] ?? []);
            $row['suggestions'] = [];
            if ($row['eligible'] && $row['kind'] !== 'pages' && $row['incoming_contextual'] === 0) {
                $this->add($row, 'contextual_orphan', 'No incoming contextual body link was found in this audit. Menus, listing cards, pagination and static-page bodies are outside the link graph, so this is a review candidate, not a confirmed unreachable page.');
                $terms = $this->terms($row['title'].' '.$row['topic']);
                $candidates = [];
                foreach ($rows as $otherKey => $other) {
                    if ($otherKey !== $key && $other['eligible'] && $other['kind'] !== 'pages') {
                        $overlap = count(array_intersect($terms, $this->terms($other['title'].' '.$other['topic'])));
                        if ($overlap > 0) {
                            $candidates[$otherKey] = $overlap;
                        }
                    }
                }
                arsort($candidates);
                foreach (array_slice(array_keys($candidates), 0, 3) as $otherKey) {
                    $row['suggestions'][] = ['title' => $rows[$otherKey]['title'], 'url' => $rows[$otherKey]['url']];
                }
            }
            $row['review_count'] = count(array_filter($row['checks'], fn (array $check): bool => $check['status'] === 'review'));
            unset($row['links'], $row['fingerprint']);
        }
        unset($row);

        return ['version' => 1, 'generated_at' => now()->toIso8601String(), 'scope' => $includeDrafts ? 'all' : 'published',
            'indexing_enabled' => (bool) config('portfolio.indexable'),
            'method' => 'Six main pages receive metadata checks only. Other checks use saved Markdown, excluding code. The contextual link graph excludes navigation, listing cards, pagination and static-page bodies. External URLs, downloads and fragment availability are not checked. Suggestions are lexical matches for human review. No content is changed.',
            'totals' => ['pages' => count($rows), 'with_reviews' => count(array_filter($rows, fn (array $row): bool => $row['review_count'] > 0))],
            'entries' => array_values($rows)];
    }

    /** @param array<string, mixed> $item
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function row(array $item, array $input, array $metadata, bool $hasBody): array
    {
        $analysis = ContentAnalysis::analyze($input, [...$metadata, 'self_url' => $item['url']], $hasBody);

        return [...$item, ...$analysis, 'title' => $input['title'] ?? SeoMetadata::PAGES[$item['id']] ?? $metadata['title'],
            'topic' => is_string($input['seo_focus_keyword'] ?? null) ? $input['seo_focus_keyword'] : '',
            'search_title' => $metadata['title'], 'search_description' => $metadata['description']];
    }

    /** @param array<string, mixed> $row */
    private function add(array &$row, string $code, string $message): void
    {
        $row['checks'][] = ['code' => $code, 'status' => 'review', 'message' => $message];
    }

    /** @param array<string, array<string, mixed>> $inventory
     * @param  array<string, string>  $aliases
     * @return array{path: string, redirected: bool}|null
     */
    private function resolve(string $path, array $inventory, array $aliases): ?array
    {
        $seen = [];
        $redirected = false;
        for ($step = 0; $step < 10; $step++) {
            if (isset($seen[$path])) {
                return null;
            }
            $seen[$path] = true;
            if (isset($aliases[$path])) {
                $path = $aliases[$path];
                $redirected = true;

                continue;
            }

            return ($inventory[$path]['public'] ?? false) ? compact('path', 'redirected') : null;
        }

        return null;
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $stop = ['the', 'and', 'for', 'with', 'from', 'your', 'this', 'that', 'how', 'using', 'into', 'guide'];

        return array_values(array_unique(array_filter(explode(' ', ContentAnalysis::normalize($text)), fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, $stop, true))));
    }

    /** @param array<string, mixed> $report */
    public function save(array $report): string
    {
        $path = Storage::disk('local')->path(self::REPORT_PATH);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 0600);
        if (! is_file($path)) {
            throw new RuntimeException('The private report could not be saved.');
        }

        return $path;
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::REPORT_PATH) || $disk->size(self::REPORT_PATH) > 20000000) {
            return null;
        }
        try {
            $report = json_decode($disk->get(self::REPORT_PATH), true, 64, JSON_THROW_ON_ERROR);

            if (! is_array($report)) {
                return null;
            }
            $validator = Validator::make($report, [
                'version' => ['required', 'integer', 'in:1'], 'generated_at' => ['required', 'date'],
                'scope' => ['required', 'in:all,published'], 'method' => ['required', 'string'], 'indexing_enabled' => ['required', 'boolean'],
                'totals.pages' => ['required', 'integer', 'min:0'], 'totals.with_reviews' => ['required', 'integer', 'min:0'],
                'entries' => ['present', 'array', 'list'], 'entries.*' => ['array'],
                'entries.*.kind' => ['required', 'in:pages,blog,projects,services'], 'entries.*.id' => ['required'],
                'entries.*.title' => ['required', 'string'], 'entries.*.url' => ['required', 'string'],
                'entries.*.topic' => ['present', 'string'], 'entries.*.state' => ['required', 'string'], 'entries.*.scope' => ['required', 'in:body,metadata'],
                'entries.*.stats.words' => ['required', 'integer', 'min:0'], 'entries.*.incoming_contextual' => ['required', 'integer', 'min:0'],
                'entries.*.review_count' => ['required', 'integer', 'min:0'], 'entries.*.checks' => ['present', 'array', 'list'],
                'entries.*.checks.*.status' => ['required', 'in:review,info'], 'entries.*.checks.*.message' => ['required', 'string'],
                'entries.*.suggestions' => ['present', 'array', 'list'], 'entries.*.suggestions.*.title' => ['required', 'string'],
                'entries.*.suggestions.*.url' => ['required', 'string'],
            ]);

            return $validator->passes() ? $report : null;
        } catch (JsonException) {
            return null;
        }
    }
}
