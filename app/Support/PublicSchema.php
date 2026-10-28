<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\Project;
use App\Models\SiteSetting;

class PublicSchema
{
    /** @return array<string, string> */
    public static function options(string $type): array
    {
        return ['auto' => 'Automatic', 'none' => 'Disable structured data on this page']
            + ($type === 'blog' ? ['BlogPosting' => 'Blog posting', 'Article' => 'Article', 'TechArticle' => 'Technical article'] : []);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    public static function build(string $page, Profile $profile, SiteSetting $site, array $data, array $metadata): ?array
    {
        $entry = $data['entry'] ?? null;
        $selection = $entry?->seo_schema_type ?? ($site->page_seo[$page]['seo_schema_type'] ?? 'auto');
        if ($selection === 'none' || ($data['isPreview'] ?? false)) {
            return null;
        }
        $section = $data['section'] ?? $page;
        $home = PublicSeo::url('home');
        $canonical = $metadata['canonical'];
        $portrait = MediaSeo::image($profile->portrait_path ?: 'assets/images/arif-formal.png', MediaSeo::text($profile->portrait_alt, $profile->name));
        $person = [
            '@type' => 'Person', '@id' => $home.'#person', 'name' => $profile->name,
            'url' => PublicSeo::url('about'),
            'sameAs' => array_values(array_unique(array_filter(array_map(PublicContent::link(...), [$site->facebook_url, $site->linkedin_url, $site->github_url])))),
        ];
        if ($portrait) {
            $person['image'] = self::imageObject($portrait, $home.'#portrait');
        }
        if (filled($profile->headline)) {
            $person['description'] = $profile->headline;
        }
        $graph = [
            ['@type' => 'WebSite', '@id' => $home.'#website', 'url' => $home, 'name' => $site->brand_name, 'publisher' => ['@id' => $home.'#person']],
        ];
        if (in_array($page, ['home', 'about'], true)) {
            $graph[] = $person;
        }
        $webPage = [
            '@type' => match ($page) {
                'about' => 'ProfilePage', 'contact' => 'ContactPage', 'blog', 'projects', 'services' => 'CollectionPage', default => 'WebPage',
            },
            '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => $metadata['title'],
            'description' => $metadata['description'], 'isPartOf' => ['@id' => $home.'#website'],
        ];
        if ($page === 'about') {
            $webPage['mainEntity'] = ['@id' => $home.'#person'];
        }
        if ($page !== 'home') {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home]];
            if ($entry) {
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => ucfirst($section), 'item' => PublicSeo::url($section)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $entry?->title ?? ucfirst($page), 'item' => $canonical];
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $canonical.'#breadcrumb', 'itemListElement' => $items];
            $webPage['breadcrumb'] = ['@id' => $canonical.'#breadcrumb'];
        }
        if ($entry && ! $entry->is_demo) {
            $content = [
                '@type' => match ($section) {
                    'blog' => in_array($selection, ['Article', 'BlogPosting', 'TechArticle'], true) ? $selection : 'BlogPosting',
                    'services' => 'Service', default => 'CreativeWork',
                },
                '@id' => $canonical.'#content', 'name' => $entry->title, 'url' => $canonical,
                'description' => $entry->excerpt ?? $entry->summary ?? '',
            ];
            $cover = $entry instanceof Project && PublicContent::video($entry->video_url) ? null
                : MediaSeo::image($entry->cover_image, MediaSeo::text($entry->cover_image_alt, $entry->title));
            if ($cover) {
                $content['image'] = self::imageObject($cover, $canonical.'#cover', $entry->cover_image_caption);
                $webPage['primaryImageOfPage'] = ['@id' => $canonical.'#cover'];
            }
            $author = ['@type' => 'Person', '@id' => $home.'#person', 'name' => $profile->name, 'url' => PublicSeo::url('about')];
            if ($section === 'blog') {
                $content += ['headline' => $entry->title, 'mainEntityOfPage' => $canonical, 'author' => $author,
                    'publisher' => ['@id' => $home.'#person'], 'dateModified' => $entry->updated_at->toIso8601String()];
                if ($entry->published_at) {
                    $content['datePublished'] = $entry->published_at->toIso8601String();
                }
            } elseif ($section === 'services') {
                $content['provider'] = $author;
                $content['serviceType'] = $entry->title;
            } else {
                $content['creator'] = $author;
                foreach ($entry->gallery ?? [] as $position => $path) {
                    $details = MediaSeo::gallery($entry, $path, $position + 1);
                    if ($image = MediaSeo::image($path, $details['alt'])) {
                        $content['associatedMedia'][] = self::imageObject($image, $canonical.'#gallery-'.($position + 1), $details['caption']);
                    }
                }
                if ($video = self::video($entry, $canonical)) {
                    $content['video'] = ['@id' => $video['@id']];
                    $webPage['video'] = ['@id' => $video['@id']];
                }
            }
            $graph[] = $content;
            $webPage['mainEntity'] = ['@id' => $content['@id']];
            if (isset($video)) {
                $graph[] = $video;
            }
        }
        if (! in_array($page, ['home', 'about'], true)) {
            $graph[] = $person;
        }
        $graph[] = $webPage;

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** @param array{url: string, alt: string, width?: int, height?: int} $image
     * @return array<string, mixed>
     */
    private static function imageObject(array $image, string $id, ?string $caption = null): array
    {
        $object = ['@type' => 'ImageObject', '@id' => $id, 'url' => $image['url'], 'contentUrl' => $image['url']];
        if (filled($image['alt'])) {
            $object['description'] = $image['alt'];
        }
        if (filled($caption)) {
            $object['caption'] = $caption;
        }
        foreach (['width', 'height'] as $key) {
            if (isset($image[$key])) {
                $object[$key] = $image[$key];
            }
        }

        return $object;
    }

    /** @return array<string, mixed>|null */
    public static function video(Project $project, string $canonical): ?array
    {
        $video = PublicContent::video($project->video_url);
        $thumbnail = MediaSeo::image($project->video_thumbnail);
        if (! $project->video_schema_enabled || ! $video || ! $thumbnail
            || ! isset($thumbnail['width']) || $thumbnail['width'] < 60 || $thumbnail['height'] < 30
            || blank($project->video_name) || blank($project->video_description)
            || ! $project->video_uploaded_at || $project->video_uploaded_at->isFuture()) {
            return null;
        }
        $schema = [
            '@type' => 'VideoObject', '@id' => $canonical.'#video', 'name' => $project->video_name,
            'description' => $project->video_description, 'thumbnailUrl' => [$thumbnail['url']],
            'uploadDate' => $project->video_uploaded_at->toIso8601String(), 'embedUrl' => $video['embed_url'],
        ];
        if ($project->video_duration_seconds > 0) {
            $schema['duration'] = 'PT'.$project->video_duration_seconds.'S';
        }

        return $schema;
    }
}
