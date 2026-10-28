<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;

class SeoMetadata
{
    public const PAGES = ['home' => 'Home', 'about' => 'About', 'services' => 'Services', 'projects' => 'Projects', 'blog' => 'Blog', 'contact' => 'Contact'];

    public const FIELDS = ['seo_title', 'seo_description', 'seo_focus_keyword', 'seo_related_keywords', 'seo_canonical_url',
        'seo_robots', 'seo_social_title', 'seo_social_description', 'seo_social_image', 'seo_social_image_alt'];

    public const ROBOTS = ['index_follow' => 'index, follow', 'index_nofollow' => 'index, nofollow',
        'noindex_follow' => 'noindex, follow', 'noindex_nofollow' => 'noindex, nofollow'];

    public const TEMPLATES = ['home' => '{title}', 'pages' => '{title} | {name}',
        'blog' => '{title} | {brand}', 'services' => '{title} | {brand}', 'projects' => '{title} | {brand}'];

    /** @return array{title: string, description: string} */
    public static function pageDefaults(string $page, Profile $profile, SiteSetting $site): array
    {
        return match ($page) {
            'home' => ['title' => $site->seo_title ?: $profile->name, 'description' => $site->seo_description ?: ($profile->home_intro ?? '')],
            'about' => ['title' => 'About', 'description' => 'Meet Arif: full-stack development, Linux administration, and cloud infrastructure.'],
            'services' => ['title' => 'Services', 'description' => 'Development, integrations, cloud infrastructure, and ongoing server care.'],
            'projects' => ['title' => 'Projects', 'description' => 'Web development and cloud infrastructure projects by Arif.'],
            'blog' => ['title' => 'Blog', 'description' => 'Practical ideas on development, WordPress, Linux, and cloud infrastructure.'],
            'contact' => ['title' => 'Contact', 'description' => 'Contact Arif Billah to discuss web development, WordPress, Laravel, Linux servers, and cloud infrastructure. Connect through WhatsApp, email, Facebook, or LinkedIn.'],
            default => ['title' => $site->brand_name, 'description' => $site->tagline ?? ''],
        };
    }

    public static function validCanonical(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\s\\\\]/', $url)) {
            return false;
        }
        $parts = parse_url($url);

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['fragment']);
    }

    public static function validTemplate(mixed $template): bool
    {
        return is_string($template) && str_contains($template, '{title}')
            && ! preg_match('/[<>\r\n{}]/', str_replace(['{title}', '{brand}', '{name}'], '', $template));
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public static function resolve(array $attributes, SiteSetting $site, Profile $profile, string $group, string $fallbackTitle, string $fallbackDescription, string $canonical, ?string $image = null, int $pageNumber = 1, bool $allowCanonicalOverride = true): array
    {
        $baseTitle = self::text($attributes['seo_title'] ?? null, $fallbackTitle);
        if ($pageNumber > 1) {
            $baseTitle .= ' — Page '.$pageNumber;
        }
        $template = $site->seo_title_templates[$group] ?? self::TEMPLATES[$group];
        if (! self::validTemplate($template)) {
            $template = self::TEMPLATES[$group];
        }
        $title = strtr($template, ['{title}' => $baseTitle, '{brand}' => $site->brand_name, '{name}' => $profile->name]);
        $description = self::text($attributes['seo_description'] ?? null, $fallbackDescription);
        if ($allowCanonicalOverride && self::validCanonical($attributes['seo_canonical_url'] ?? null)) {
            $canonical = $attributes['seo_canonical_url'];
        }
        $socialImage = $attributes['seo_social_image'] ?? null;
        if (is_string($socialImage) && ($socialUrl = PublicContent::image($socialImage))) {
            $image = self::absoluteImage($socialUrl);
        }

        return [
            'title' => $title, 'description' => $description, 'canonical' => $canonical, 'image' => $image,
            'social_title' => self::text($attributes['seo_social_title'] ?? null, $title),
            'social_description' => self::text($attributes['seo_social_description'] ?? null, $description),
            'image_alt' => self::text($attributes['seo_social_image_alt'] ?? null, $title),
            'robots' => self::ROBOTS[is_string($robots = $attributes['seo_robots'] ?? null) ? $robots : ''] ?? null,
        ];
    }

    private static function text(mixed $value, string $fallback): string
    {
        return is_string($value) && filled($value) ? $value : $fallback;
    }

    public static function absoluteImage(string $url): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    }

    public static function indexableQuery(Builder $query, string $section): Builder
    {
        $query->where(fn (Builder $query): Builder => $query->whereNull('seo_robots')->orWhereNotIn('seo_robots', ['noindex_follow', 'noindex_nofollow']));
        $overrides = (clone $query)->whereNotNull('seo_canonical_url')->where('seo_canonical_url', '!=', '')
            ->get(['id', 'slug', 'seo_canonical_url']);
        $excluded = $overrides->reject(fn ($record): bool => self::sitemapAllowed(
            $record->getAttributes(), PublicSeo::url($section.'.show', ['slug' => $record->slug]),
        ))->modelKeys();

        return $excluded ? $query->whereNotIn('id', $excluded) : $query;
    }

    /** @param array<string, mixed> $attributes */
    public static function sitemapAllowed(array $attributes, string $url): bool
    {
        if (in_array($attributes['seo_robots'] ?? null, ['noindex_follow', 'noindex_nofollow'], true)) {
            return false;
        }
        $canonical = $attributes['seo_canonical_url'] ?? null;

        return ! self::validCanonical($canonical) || rtrim($canonical, '/') === rtrim($url, '/');
    }
}
