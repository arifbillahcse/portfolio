<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\SiteSetting;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicSeo
{
    public static function url(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim(route($name, $parameters, false), '/');
    }

    public static function build(string $page, Profile $profile, SiteSetting $site, array $data): array
    {
        $section = $data['section'] ?? $page;
        $parameters = request()->route()?->parameters() ?? [];
        if (in_array($page, ['projects', 'blog'], true)) {
            if (! empty($data['category'])) {
                $parameters['category'] = $data['category'];
            }
            if (! empty($data['search'])) {
                $parameters['q'] = $data['search'];
            }
            if (request()->integer('page', 1) > 1) {
                $parameters['page'] = request()->integer('page');
            }
        }
        $entry = $data['entry'] ?? null;
        $isPreview = (bool) ($data['isPreview'] ?? false);
        $canonical = $isPreview && $entry
            ? self::url($section.'.show', ['slug' => $entry->slug])
            : self::url(request()->route()->getName(), $parameters);
        $imagePath = PublicContent::image($entry?->cover_image ?? $profile->portrait_path);
        $image = $imagePath
            ? rtrim(config('app.url'), '/').'/'.ltrim(parse_url($imagePath, PHP_URL_PATH) ?? '', '/')
            : rtrim(config('app.url'), '/').'/assets/images/arif-formal.png';
        $home = self::url('home');
        $person = [
            '@type' => 'Person', '@id' => $home.'#person', 'name' => $profile->name,
            'url' => self::url('about'), 'image' => $image,
            'sameAs' => array_values(array_filter(array_map(PublicContent::link(...), [$site->facebook_url, $site->linkedin_url, $site->github_url]))),
        ];
        $graph = [
            ['@type' => 'WebSite', '@id' => $home.'#website', 'url' => $home, 'name' => $site->brand_name],
        ];
        if (in_array($page, ['home', 'about'], true)) {
            $graph[] = $person;
        }
        if ($entry) {
            $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => ucfirst($section), 'item' => self::url($section)],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $entry->title, 'item' => $canonical]];
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs];
            if (! $entry->is_demo) {
                $content = [
                    '@type' => match ($section) {
                        'blog' => 'BlogPosting', 'services' => 'Service', default => 'CreativeWork'
                    },
                    'name' => $entry->title, 'url' => $canonical,
                    'description' => $entry->seo_description ?: ($entry->excerpt ?? $entry->summary ?? ''),
                ];
                if ($section === 'blog') {
                    $content += ['headline' => $entry->title, 'image' => $image, 'mainEntityOfPage' => $canonical,
                        'author' => ['@type' => 'Person', 'name' => $profile->name, 'url' => self::url('about')],
                        'dateModified' => $entry->updated_at->toIso8601String()];
                    if ($entry->published_at) {
                        $content['datePublished'] = $entry->published_at->toIso8601String();
                    }
                } elseif ($section === 'services') {
                    $content['provider'] = ['@type' => 'Person', 'name' => $profile->name, 'url' => self::url('about')];
                }
                $graph[] = $content;
            }
        }

        $collection = $data['projects'] ?? $data['posts'] ?? null;
        $emptyCollection = $collection instanceof LengthAwarePaginator && $collection->isEmpty();

        return [
            'is_preview' => $isPreview,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $section === 'blog' && $entry ? 'article' : 'website',
            'robots' => $isPreview ? 'noindex, nofollow' : ($emptyCollection || ! empty($data['search']) || ! empty($data['category']) || ($page === 'projects' && empty($data['hasOriginalProjects'])) ? 'noindex, follow' : null),
            'schema' => ['@context' => 'https://schema.org', '@graph' => $graph],
        ];
    }
}
