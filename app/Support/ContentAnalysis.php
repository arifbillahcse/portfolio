<?php

namespace App\Support;

use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;

class ContentAnalysis
{
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtolower($text)) ?? '');
    }

    public static function contains(string $text, string $phrase): bool
    {
        $phrase = self::normalize($phrase);

        return $phrase !== '' && str_contains(' '.self::normalize($text).' ', ' '.$phrase.' ');
    }

    public static function words(string $text): int
    {
        return preg_match_all("/[\p{L}\p{N}][\p{L}\p{M}\p{N}'’_-]*/u", $text);
    }

    /** @return array{kind: string, url: string, path?: string}|null */
    public static function link(string $href, string $currentUrl): ?array
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(?:mailto|tel):/i', $href)) {
            return null;
        }
        try {
            $url = UriResolver::resolve(new Uri($currentUrl), new Uri($href))->withFragment('');
            if (! in_array($url->getScheme(), ['http', 'https'], true) || $url->getUserInfo() !== '') {
                return null;
            }
            $home = new Uri(PublicSeo::url('home'));
            if (strtolower($url->getHost()) !== strtolower($home->getHost()) || $url->getPort() !== $home->getPort()) {
                return ['kind' => 'external', 'url' => (string) $url];
            }
            $path = '/'.trim(rawurldecode($url->getPath()), '/');
            $base = rtrim($home->getPath(), '/');
            if ($base !== '') {
                if ($path !== $base && ! str_starts_with($path, $base.'/')) {
                    return ['kind' => 'external', 'url' => (string) $url];
                }
                $path = '/'.ltrim(substr($path, strlen($base)), '/');
            }
            if (preg_match('/[\x00-\x20\x7f\\\\]/', $path) || str_contains($path, '//')) {
                return null;
            }
            if ($path === '/') {
                parse_str($url->getQuery(), $query);
                foreach (['p', 'page_id'] as $key) {
                    if (isset($query[$key])) {
                        if (! is_string($query[$key]) || ! preg_match('/\A[1-9][0-9]*\z/', $query[$key])) {
                            return null;
                        }
                        $path .= '?'.$key.'='.$query[$key];
                        break;
                    }
                }
            }

            return ['kind' => 'internal', 'url' => (string) $url, 'path' => $path];
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function analyze(array $input, array $metadata, bool $hasBody = true): array
    {
        $checks = [];
        $add = function (string $code, string $status, string $message) use (&$checks): void {
            $checks[] = compact('code', 'status', 'message');
        };
        foreach (['title', 'description'] as $field) {
            if (blank($metadata[$field] ?? null)) {
                $add('missing_'.$field, 'review', 'The effective search '.$field.' is empty. Add useful text or a content fallback.');
            }
        }
        if (mb_strlen($metadata['title'] ?? '') > 65 || mb_strlen($metadata['description'] ?? '') > 170) {
            $add('metadata_length', 'info', 'Search text may be shortened. Character counts are an editorial guide, not a Google limit.');
        }
        $robots = $input['seo_robots'] ?? '';
        if (in_array($robots, ['noindex_follow', 'noindex_nofollow'], true)) {
            $add('noindex', 'info', 'This page is explicitly noindex. Confirm that this is intentional.');
        }
        $canonical = $input['seo_canonical_url'] ?? null;
        if (SeoMetadata::validCanonical($canonical) && rtrim($canonical, '/') !== rtrim($metadata['self_url'] ?? $metadata['canonical'], '/')) {
            $add('canonical_override', 'info', 'A different preferred URL is set. This page is excluded from the sitemap; a canonical does not redirect visitors.');
        }
        $stats = ['words' => 0, 'paragraphs' => 0, 'headings' => 0, 'images' => 0, 'missing_alt' => 0, 'internal_links' => 0, 'external_links' => 0, 'code_blocks' => 0];
        $outline = [];
        $links = [];
        $text = '';
        $intro = '';
        $truncated = false;
        if ($hasBody) {
            $body = is_string($input['body'] ?? null) ? $input['body'] : '';
            if (mb_strlen($body) > 100000) {
                $body = mb_substr($body, 0, 100000);
                $truncated = true;
                $add('analysis_limit', 'review', 'This analysis covers only the first 100,000 characters. Review the remainder manually. A full-body duplicate comparison is unavailable.');
            }
            ['document' => $document, 'root' => $root] = MediaSeo::document(MediaSeo::body(
                $body,
                is_array($input['body_image_metadata'] ?? null) ? $input['body_image_metadata'] : null,
            ));
            $stats['code_blocks'] = $root->getElementsByTagName('pre')->length;
            foreach (iterator_to_array($root->getElementsByTagName('pre')) as $node) {
                $node->parentNode->replaceChild($document->createTextNode(' '), $node);
            }
            $xpath = new DOMXPath($document);
            $headings = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $root);
            $previousLevel = 1;
            $skipped = 0;
            $extraH1 = 0;
            $commandHeadings = 0;
            foreach ($headings as $heading) {
                $level = (int) substr($heading->nodeName, 1);
                $label = trim($heading->textContent);
                $extraH1 += (int) ($level === 1);
                $skipped += (int) ($level > $previousLevel + 1);
                $previousLevel = $level;
                $commandHeadings += (int) preg_match('/^(?:sudo|apt(?:-get)?|yum|dnf|systemctl|docker|chmod|chown|curl|wget)\b/i', $label);
                $outline[] = ['level' => $level, 'text' => mb_substr($label, 0, 200)];
            }
            $stats['headings'] = count($outline);
            if ($extraH1 > 0) {
                $add('extra_h1', 'review', $extraH1.' H1 heading(s) appear inside the body. The page template already provides the main H1. Review the hierarchy and imported shell comments.');
            }
            if ($skipped > 0) {
                $add('heading_order', 'review', $skipped.' heading level jump(s) found. Review the outline for a clear hierarchy.');
            }
            if ($commandHeadings > 0) {
                $add('command_heading', 'review', 'A heading looks like a shell command. Review the source and use a fenced code block where appropriate; commands have not been executed or verified.');
            }
            if ($stats['code_blocks'] > 0) {
                $add('code_review', 'info', 'Code blocks are excluded from prose, keyword and link counts. Manually verify command safety and accuracy before publishing.');
            }
            $genericAnchors = 0;
            foreach ($root->getElementsByTagName('a') as $anchor) {
                $href = $anchor->getAttribute('href');
                $link = self::link($href, $metadata['self_url'] ?? $metadata['canonical']);
                if (! $link) {
                    continue;
                }
                $stats[$link['kind'].'_links']++;
                $label = trim($anchor->textContent);
                foreach ($anchor->getElementsByTagName('img') as $image) {
                    $label .= ' '.$image->getAttribute('alt');
                }
                if (in_array(self::normalize($label), ['', 'click here', 'here', 'read more', 'learn more'], true)) {
                    $genericAnchors++;
                }
                if ($link['kind'] === 'internal') {
                    $links[$link['path']] = $link;
                }
            }
            if ($genericAnchors > 0) {
                $add('anchor_text', 'review', $genericAnchors.' link(s) have empty or generic anchor text. Explain the destination in context.');
            }
            foreach ($root->getElementsByTagName('img') as $image) {
                $stats['images']++;
                if (blank($image->getAttribute('alt')) && $image->getAttribute('role') !== 'presentation') {
                    $stats['missing_alt']++;
                }
            }
            if ($stats['missing_alt'] > 0) {
                $add('image_alt', 'review', $stats['missing_alt'].' informative image(s) lack a description. Write accurate alt text or explicitly mark decoration in image metadata.');
            }
            if (filled($input['cover_image'] ?? null) && blank($input['cover_image_alt'] ?? null)) {
                $add('cover_alt', 'info', 'The cover uses the content title as fallback alt text. Review whether a specific image description would be more useful.');
            }
            foreach (iterator_to_array($root->getElementsByTagName('code')) as $node) {
                $node->parentNode->replaceChild($document->createTextNode(' '), $node);
            }
            $text = trim(self::prose($root));
            $stats['words'] = self::words($text);
            $longParagraphs = 0;
            foreach ($root->getElementsByTagName('p') as $paragraph) {
                $paragraphText = trim(self::prose($paragraph));
                if ($paragraphText !== '') {
                    $stats['paragraphs']++;
                    if ($intro === '') {
                        $intro = $paragraphText;
                    }
                    $longParagraphs += (int) (self::words($paragraphText) > 120);
                }
            }
            if ($text === '') {
                $add('body_empty', 'review', 'No explanatory prose was found. Add the information a client or reader needs; code alone does not explain the work.');
            } elseif (self::normalize($text) === self::normalize($input['summary'] ?? '') && filled($input['summary'] ?? null)) {
                $add('summary_only', 'review', 'The body repeats the summary. Explain the problem, real scope, process or supporting evidence where relevant.');
            }
            if ($longParagraphs > 0) {
                $add('long_paragraphs', 'info', $longParagraphs.' paragraph(s) exceed 120 estimated words. Consider splitting for readability; this is not a ranking rule.');
            }
            if ($stats['headings'] === 0 && $text !== '') {
                $add('subheadings', 'info', 'No body subheadings were found. Consider whether sections would help readers; a short page may not need them.');
            }
            if ($stats['internal_links'] === 0) {
                $add('internal_links', 'info', 'No contextual internal links were found in the body. Add relevant services, projects or articles when they help the reader.');
            }
        }
        $focus = is_string($input['seo_focus_keyword'] ?? null) ? trim($input['seo_focus_keyword']) : '';
        if ($focus !== '') {
            $locations = [];
            $fields = ['search title' => $metadata['title'] ?? '', 'description' => $metadata['description'] ?? '',
                'URL path' => rawurldecode(parse_url($metadata['self_url'] ?? $metadata['canonical'], PHP_URL_PATH) ?? '')];
            if ($hasBody) {
                $fields += ['main heading' => $input['title'] ?? '', 'introduction' => $intro, 'body' => $text,
                    'body headings' => implode(' ', array_column($outline, 'text'))];
            }
            foreach ($fields as $name => $value) {
                if (self::contains($value, $focus)) {
                    $locations[] = $name;
                }
            }
            $add('focus_topic', 'info', 'Normalized focus phrase “'.$focus.'” found in '.($locations ? implode(', ', $locations) : 'none of the checked fields').'. Matching ignores case, punctuation and spacing; natural wording and synonyms are valid. There is no density target.');
        } else {
            $add('focus_topic', 'info', 'An optional focus topic can help plan this page. It is a private editorial note, not a meta keyword or ranking signal.');
        }
        $related = array_slice(array_values(array_filter(is_array($input['seo_related_keywords'] ?? null) ? $input['seo_related_keywords'] : [], 'is_string')), 0, 15);
        if ($related && $hasBody) {
            $found = array_filter($related, fn (string $term): bool => self::contains($text, $term));
            $add('related_topics', 'info', count($found).' of '.count($related).' related topic phrase(s) occur literally in the prose. Use relevant concepts naturally; this does not assess semantic coverage.');
        }

        return ['checks' => $checks, 'stats' => $stats, 'outline' => array_slice($outline, 0, 20), 'outline_total' => count($outline),
            'links' => array_values($links), 'fingerprint' => ! $truncated && self::normalize($text) !== '' ? hash('sha256', self::normalize($text)) : null,
            'scope' => $hasBody ? 'body' : 'metadata'];
    }

    private static function prose(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::prose($child);
        }
        if ($node instanceof DOMElement && in_array($node->tagName, ['p', 'li', 'div', 'blockquote', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $text .= ' ';
        }

        return $text;
    }
}
