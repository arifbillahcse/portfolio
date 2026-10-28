<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WordPressMedia
{
    public function download(string $url): string
    {
        $url = $this->sourceUrl($url);
        $prefix = 'posts/wordpress/'.hash('sha256', $url);
        foreach (['jpg', 'png', 'webp', 'gif'] as $extension) {
            if (Storage::disk('public')->exists($prefix.'.'.$extension)) {
                return $prefix.'.'.$extension;
            }
        }
        $response = Http::connectTimeout(5)->timeout(18)
            ->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp,image/gif'])
            ->withOptions(['allow_redirects' => false, 'stream' => true])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Image server returned HTTP '.$response->status().'.');
        }
        $stream = $response->toPsrResponse()->getBody();
        $contents = '';
        try {
            while (! $stream->eof()) {
                $contents .= $stream->read(65536);
                if (strlen($contents) > config('wordpress.max_image_bytes')) {
                    throw new RuntimeException('Image exceeds the 8 MB limit.');
                }
            }
        } finally {
            $stream->close();
        }
        $info = @getimagesizefromstring($contents);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (! $info || ! isset($extensions[$info['mime']]) || $info[0] * $info[1] > 40000000) {
            throw new RuntimeException('Only valid JPG, PNG, WebP or GIF images up to 40 megapixels are accepted.');
        }
        $path = $prefix.'.'.$extensions[$info['mime']];
        Storage::disk('public')->makeDirectory('posts/wordpress');
        Storage::disk('public')->setVisibility('posts', 'public');
        Storage::disk('public')->setVisibility('posts/wordpress', 'public');
        if (! Storage::disk('public')->put($path, $contents, ['visibility' => 'public'])) {
            throw new RuntimeException('Image could not be saved to public storage.');
        }

        return $path;
    }

    public function sourceUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (str_starts_with($url, '/')) {
            $url = 'https://'.config('wordpress.source_host').$url;
        }
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        if (! $parts || ! in_array(strtolower($parts['host'] ?? ''), config('wordpress.image_hosts'), true)
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! str_starts_with($path, '/wp-content/uploads/')
            || preg_match('/[\x00-\x20\\\\]/', rawurldecode($path)) || str_contains(rawurldecode($path), '..')) {
            throw new RuntimeException('Image URL must point to wp-content/uploads on the configured WordPress site.');
        }

        return 'https://'.config('wordpress.source_host').$path;
    }
}
