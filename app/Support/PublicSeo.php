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
        $defaults = $entry ? ['title' => $entry->title, 'description' => $entry->excerpt ?? $entry->summary ?? '']
            : SeoMetadata::pageDefaults($page, $profile, $site);
        $pageNumber = ! $entry && in_array($page, ['blog', 'projects'], true) ? max(1, request()->integer('page', 1)) : 1;
        $metadata = SeoMetadata::resolve(
            $entry ? $entry->getAttributes() : ($site->page_seo[$page] ?? []), $site, $profile,
            $entry ? $section : ($page === 'home' ? 'home' : 'pages'),
            $defaults['title'], $defaults['description'], $canonical, $image, $pageNumber,
            ! $isPreview && $pageNumber === 1 && empty($data['search']) && empty($data['category']),
        );
        $canonical = $metadata['canonical'];
        $image = $metadata['image'];
        $attributes = $entry ? $entry->getAttributes() : ($site->page_seo[$page] ?? []);
        if (blank($attributes['seo_social_image_alt'] ?? null) && blank($attributes['seo_social_image'] ?? null)) {
            $metadata['image_alt'] = $entry?->cover_image
                ? MediaSeo::text($entry->cover_image_alt, $entry->title)
                : MediaSeo::text($profile->portrait_alt, $profile->name);
        }
        $collection = $data['projects'] ?? $data['posts'] ?? null;
        $emptyCollection = $collection instanceof LengthAwarePaginator && $collection->isEmpty();
        $restricted = $entry?->is_demo || $emptyCollection || ! empty($data['search']) || ! empty($data['category'])
            || ($page === 'projects' && empty($data['hasOriginalProjects']))
            || ($page === 'blog' && empty($data['hasOriginalPosts']));

        return [
            ...$metadata,
            'is_preview' => $isPreview,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $section === 'blog' && $entry ? 'article' : 'website',
            'robots' => ! config('portfolio.indexable') || $isPreview ? 'noindex, nofollow'
                : ($restricted ? 'noindex, follow' : $metadata['robots']),
            'image_dimensions' => app(MediaSeo::class)->size($image),
            'schema' => PublicSchema::build($page, $profile, $site, $data, $metadata),
        ];
    }
}
