<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RenderedContent
{
    public static function body(Model $entry, bool $preview = false): string
    {
        $render = fn (): string => MediaSeo::body($entry->body, $entry->body_image_metadata);
        if ($preview || ! config('performance.cache_rendered_content')) {
            return $render();
        }
        $key = 'portfolio:body:v1:'.hash('sha256', json_encode([
            $entry->body, $entry->body_image_metadata, config('app.url'), config('filesystems.disks.public.url'),
            app(ResponsiveImages::class)->version(),
        ], JSON_THROW_ON_ERROR));

        try {
            return Cache::remember($key, 3600, $render);
        } catch (Throwable $exception) {
            report($exception);

            return $render();
        }
    }
}
