<?php

namespace App\Support;

use App\Models\Project;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaSeo
{
    /** @var array<string, array{width: int, height: int}|null> */
    private array $dimensions = [];

    public static function text(mixed $value, string $fallback = ''): string
    {
        return is_string($value) && filled($value) ? trim($value) : $fallback;
    }

    /** @return array{url: string, alt: string, width?: int, height?: int}|null */
    public static function image(?string $path, string $alt = ''): ?array
    {
        $url = PublicContent::image($path);
        if (! $url && $path && preg_match('~\Aassets/images/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|gif)\z~i', $path)) {
            $url = PublicSeo::url('home').$path;
        }
        if (! $url) {
            return null;
        }

        return ['url' => SeoMetadata::absoluteImage($url), 'alt' => $alt, ...(app(self::class)->size($url) ?? [])];
    }

    /** @return array{width: int, height: int}|null */
    public function size(string $source): ?array
    {
        $path = $this->localPath($source);
        if ($path === null) {
            return null;
        }
        if (! array_key_exists($path, $this->dimensions)) {
            $size = is_file($path) ? @getimagesize($path) : false;
            $this->dimensions[$path] = $size && in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)
                ? ['width' => $size[0], 'height' => $size[1]] : null;
        }

        return $this->dimensions[$path];
    }

    private function localPath(string $source): ?string
    {
        $parts = parse_url($source);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['host']) && strtolower($parts['host']) !== strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?? ''))) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '');
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return null;
        }
        $base = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        $path = $base && str_starts_with($path, $base.'/') ? substr($path, strlen($base)) : $path;
        if (str_starts_with($path, '/storage/')) {
            $relative = substr($path, 9);
            if (PublicContent::image($relative) && config('filesystems.disks.public.driver') === 'local') {
                return Storage::disk('public')->path($relative);
            }
        }
        if (preg_match('~\A/assets/images/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|gif)\z~i', $path)) {
            return public_path(ltrim($path, '/'));
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function gallery(Model $entry, string $path, int $position): array
    {
        $details = collect($entry->gallery_metadata ?? [])->first(fn (mixed $item): bool => is_array($item) && ($item['src'] ?? null) === $path) ?? [];

        return ['alt' => self::text($details['alt'] ?? null, $entry->title.' — screenshot '.$position),
            'caption' => self::text($details['caption'] ?? null)];
    }

    /** @return array{document: DOMDocument, root: DOMElement} */
    public static function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="media-content">'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = (new DOMXPath($document))->query('//div[@id="media-content"]')->item(0);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return ['document' => $document, 'root' => $root];
    }

    public static function body(?string $markdown, ?array $metadata = null): string
    {
        $html = PublicContent::markdown($markdown);
        if (! str_contains($html, '<img')) {
            return $html;
        }
        ['document' => $document, 'root' => $root] = self::document($html);
        foreach ($root->getElementsByTagName('img') as $image) {
            $source = $image->getAttribute('src');
            $details = collect($metadata ?? [])->first(fn (mixed $item): bool => is_array($item) && ($item['src'] ?? null) === $source);
            if ($details) {
                if (($details['decorative'] ?? false) === true) {
                    $image->setAttribute('alt', '');
                    $image->setAttribute('role', 'presentation');
                } elseif (is_string($details['alt'] ?? null) && filled($details['alt'])) {
                    $image->setAttribute('alt', $details['alt']);
                    $image->removeAttribute('role');
                }
            }
            foreach (app(self::class)->size($source) ?? [] as $key => $value) {
                $image->setAttribute($key, (string) $value);
            }
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
        }
        $result = '';
        foreach ($root->childNodes as $node) {
            $result .= $document->saveHTML($node);
        }

        return $result;
    }

    /** @return list<string> */
    public static function sitemapImages(Model $entry): array
    {
        $images = [];
        $paths = $entry->gallery ?? [];
        if (! ($entry instanceof Project && PublicContent::video($entry->video_url))) {
            $paths[] = $entry->cover_image;
        }
        foreach ($paths as $path) {
            if (($image = self::image($path)) && isset($image['width'])) {
                $images[] = $image['url'];
            }
        }
        $html = PublicContent::markdown($entry->body);
        if (str_contains($html, '<img')) {
            ['root' => $root] = self::document($html);
            foreach ($root->getElementsByTagName('img') as $image) {
                $source = $image->getAttribute('src');
                if (app(self::class)->size($source)) {
                    $images[] = SeoMetadata::absoluteImage($source);
                }
            }
        }

        return array_slice(array_values(array_unique($images)), 0, 1000);
    }
}
