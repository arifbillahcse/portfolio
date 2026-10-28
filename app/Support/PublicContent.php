<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicContent
{
    public static function markdown(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);
    }

    public static function image(?string $path): ?string
    {
        if (! $path || ! preg_match('#^(projects|posts|profile)/[a-zA-Z0-9_./-]+\.(png|jpe?g|webp|gif)$#i', $path) || str_contains($path, '..')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function link(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
