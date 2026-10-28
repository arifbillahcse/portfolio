<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WordPressXml
{
    private int $removedCharacters = 0;

    /** @return array<int, array<string, mixed>> */
    public function read(string $path, bool $cleanInvalidCharacters = false): array
    {
        $this->removedCharacters = 0;
        if (! is_file($path) || filesize($path) > config('wordpress.max_xml_bytes')) {
            throw ValidationException::withMessages(['xml' => 'Choose a WordPress XML export up to 8 MB.']);
        }
        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['xml' => 'The XML file is empty or could not be read. Upload a fresh WordPress export.']);
        }
        $prefix = preg_replace('/^\xEF\xBB\xBF/', '', substr($contents, 0, 2048));
        if (preg_match('/^\s*(?:<\?xml[^?]*\?>\s*)?(?:<!doctype\s+html\b|<html\b)/i', $prefix)) {
            throw ValidationException::withMessages(['xml' => 'This is an HTML page, not a WordPress XML export. Download the file from WordPress Tools → Export → All content.']);
        }
        if ($cleanInvalidCharacters) {
            $declaresEncoding = preg_match('/\A(?:\xEF\xBB\xBF)?\s*<\?xml\b[^?]*\bencoding\s*=\s*["\x27]([^"\x27]+)["\x27]/i', $contents, $encoding);
            if (! mb_check_encoding($contents, 'UTF-8') || str_contains(substr($contents, 0, 4), "\0")
                || ($declaresEncoding && ! in_array(strtoupper($encoding[1]), ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true))) {
                throw ValidationException::withMessages(['xml' => 'Character cleanup supports UTF-8 exports only. Re-export as UTF-8; no file was changed.']);
            }
            $contents = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{FFFE}\x{FFFF}]/u', '', $contents, -1, $this->removedCharacters);
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw ValidationException::withMessages(['xml' => 'XML document types and entities are not allowed.']);
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
                $messages = ['This file is not valid XML. No posts have been imported.'];
                foreach (array_slice(array_filter(libxml_get_errors(), fn (\LibXMLError $error): bool => $error->level >= LIBXML_ERR_ERROR), 0, 3) as $error) {
                    $reason = trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', $error->message));
                    $messages[] = 'XML line '.$error->line.', column '.$error->column.': '.Str::limit($reason, 240);
                }
                $messages[] = 'Download a fresh WXR export, or use these line numbers to repair the original file. The importer does not discard invalid content.';
                throw ValidationException::withMessages(['xml' => $messages]);
            }
            if ($document->doctype) {
                throw ValidationException::withMessages(['xml' => 'XML document types and entities are not allowed.']);
            }
            $xpath = new DOMXPath($document);
            $namespace = $document->documentElement?->lookupNamespaceURI('wp');
            if (! $namespace || ! str_starts_with($namespace, 'http://wordpress.org/export/')) {
                throw ValidationException::withMessages(['xml' => 'Use WordPress Tools → Export → All content (WXR XML).']);
            }
            $xpath->registerNamespace('wp', $namespace);
            $attachments = [];
            foreach ($xpath->query('/rss/channel/item[wp:post_type="attachment"]') as $node) {
                $attachments[$xpath->evaluate('string(wp:post_id)', $node)] = $xpath->evaluate('string(wp:attachment_url)', $node);
            }
            $posts = [];
            foreach ($xpath->query('/rss/channel/item[wp:post_type="post"]') as $node) {
                if (in_array($xpath->evaluate('string(wp:status)', $node), ['trash', 'auto-draft', 'inherit'], true)) {
                    continue;
                }
                $id = $xpath->evaluate('string(wp:post_id)', $node);
                if (! ctype_digit($id) || (int) $id < 1) {
                    throw ValidationException::withMessages(['xml' => 'A post is missing its WordPress ID.']);
                }
                $html = $xpath->evaluate('string(*[local-name()="encoded" and namespace-uri()="http://purl.org/rss/1.0/modules/content/"])', $node);
                $thumbnail = $xpath->evaluate('string(wp:postmeta[wp:meta_key="_thumbnail_id"]/wp:meta_value)', $node);
                $cover = $attachments[$thumbnail] ?? '';
                $html = preg_replace_callback('/\[gallery\b[^\]]*ids=["\x27]([0-9, ]+)["\x27][^\]]*\]/i', function (array $match) use ($attachments): string {
                    $markup = '';
                    foreach (explode(',', $match[1]) as $attachmentId) {
                        if ($url = $attachments[trim($attachmentId)] ?? null) {
                            $markup .= '<p><img src="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" alt="Gallery image"></p>';
                        }
                    }

                    return $markup;
                }, $html);
                $images = array_values(array_unique(array_filter([$cover, ...WordPressImages::extract($html)])));
                $category = $xpath->query('category[@domain="category"]', $node)->item(0);
                $posts[] = [
                    'wordpress_id' => (int) $id,
                    'title' => mb_substr(trim($xpath->evaluate('string(title)', $node)) ?: 'Untitled post '.$id, 0, 255),
                    'slug' => rawurldecode($xpath->evaluate('string(wp:post_name)', $node)),
                    'link' => $xpath->evaluate('string(link)', $node),
                    'source_host' => parse_url($xpath->evaluate('string(/rss/channel/wp:base_site_url)'), PHP_URL_HOST),
                    'date' => $xpath->evaluate('string(wp:post_date_gmt)', $node),
                    'html' => $html,
                    'excerpt' => $xpath->evaluate('string(*[local-name()="encoded" and contains(namespace-uri(), "/excerpt/")])', $node),
                    'category' => $category instanceof DOMElement ? ['name' => $category->textContent, 'slug' => $category->getAttribute('nicename')] : null,
                    'cover' => $cover,
                    'media' => array_map(fn (string $url): array => ['url' => $url, 'path' => null, 'attempted' => false, 'error' => null], $images),
                    'notes' => array_values(array_filter([
                        $this->removedCharacters ? 'Source XML cleanup removed '.$this->removedCharacters.' forbidden characters in memory. Original export file unchanged; review this post before publishing.' : null,
                        $xpath->query('category[@domain="category"]', $node)->length > 1 ? 'Only the first category is used; this blog supports one category per post.' : null,
                        $xpath->query('category[@domain="post_tag"]', $node)->length > 0 ? 'Tags are not imported; the blog currently has no tag field.' : null,
                        preg_match('/\[(?:\/?[a-zA-Z_])[\w\s="\x27,.:\/-]*\]/', $html) ? 'Review WordPress shortcodes or page-builder content before publishing.' : null,
                        $thumbnail && ! $cover ? 'Featured image is absent from the export. Export All content to include attachment records.' : null,
                    ])),
                ];
            }
            if ($posts === []) {
                throw ValidationException::withMessages(['xml' => 'No importable blog posts were found. Pages and attachments alone are not imported.']);
            }

            return $posts;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function removedCharacters(): int
    {
        return $this->removedCharacters;
    }
}
