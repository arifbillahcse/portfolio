<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

class WordPressImages
{
    /** @return list<string> */
    public static function extract(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $images = [];
            foreach ($document->getElementsByTagName('img') as $image) {
                $url = self::candidates($image)[0] ?? null;
                if ($url) {
                    $images[] = $url;
                }
            }

            return array_values(array_unique($images));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return list<string> */
    public static function candidates(DOMElement $image): array
    {
        $urls = [];
        foreach (['data-lazy-src', 'data-src', 'data-original', 'data-orig-file', 'data-full-url'] as $attribute) {
            $urls[] = $image->getAttribute($attribute);
        }
        foreach (['data-lazy-srcset', 'data-srcset', 'srcset'] as $attribute) {
            $urls = [...$urls, ...self::srcset($image->getAttribute($attribute))];
        }
        for ($picture = $image->parentNode; $picture instanceof DOMElement; $picture = $picture->parentNode) {
            if (strtolower($picture->tagName) === 'picture') {
                foreach ($picture->getElementsByTagName('source') as $source) {
                    $urls = [...$urls, ...self::srcset($source->getAttribute('data-srcset') ?: $source->getAttribute('srcset'))];
                }
                break;
            }
        }
        $urls[] = $image->getAttribute('src');

        return array_values(array_unique(array_filter(array_map(fn (string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $urls),
            fn (string $url): bool => (bool) preg_match('#^(?:https?://|//|/|wp-content/uploads/)#i', $url))));
    }

    /** @return list<string> */
    private static function srcset(string $value): array
    {
        $sources = [];
        foreach (preg_split('/\s*,\s*/', trim($value), flags: PREG_SPLIT_NO_EMPTY) as $source) {
            if (preg_match('/^(\S+)(?:\s+([0-9.]+)[wx])?$/', trim($source), $match)) {
                $sources[] = ['url' => $match[1], 'size' => (float) ($match[2] ?? 1)];
            }
        }
        usort($sources, fn (array $first, array $second): int => $second['size'] <=> $first['size']);

        return array_column($sources, 'url');
    }
}
