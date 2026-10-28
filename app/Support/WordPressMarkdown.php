<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;

class WordPressMarkdown
{
    /** @param array<string, string> $images */
    public function convert(string $html, array $images): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);
            $markdown = $body ? $this->node($body, $images) : '';

            return trim(preg_replace('/\n{3,}/', "\n\n", $markdown));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param array<string, string> $images */
    private function node(DOMNode $node, array $images): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/([\\\\`*_\[\]<>])/', '\\\\$1', $node->textContent);
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'svg'], true)) {
            return '';
        }
        if ($tag === 'pre') {
            $fence = str_repeat('`', max(3, $this->longestBacktickRun($node->textContent) + 1));

            return "\n\n".$fence."\n".trim($node->textContent)."\n".$fence."\n\n";
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->node($child, $images);
        }
        if (preg_match('/^h([1-6])$/', $tag, $match)) {
            return "\n\n".str_repeat('#', (int) $match[1]).' '.trim($text)."\n\n";
        }
        if ($tag === 'img') {
            $original = $node->getAttribute('data-src') ?: $node->getAttribute('src');
            $url = isset($images[$original]) ? Storage::disk('public')->url($images[$original]) : $original;

            return $this->safeUrl($url) ? '!['.str_replace(['[', ']', "\n"], '', $node->getAttribute('alt')).'](<'.$this->encodeUrl($url).'>)' : '';
        }
        if ($tag === 'a') {
            $original = $node->getAttribute('href');
            $url = isset($images[$original]) ? Storage::disk('public')->url($images[$original]) : $original;

            return $this->safeUrl($url) ? '['.trim($text).'](<'.$this->encodeUrl($url).'>)' : $text;
        }

        return match ($tag) {
            'strong', 'b' => '**'.trim($text).'**',
            'em', 'i' => '*'.trim($text).'*',
            'code' => '`'.str_replace('`', '', $node->textContent).'`',
            'br' => "  \n",
            'hr' => "\n\n---\n\n",
            'li' => "\n- ".trim($text),
            'blockquote' => "\n\n> ".str_replace("\n", "\n> ", trim($text))."\n\n",
            'td', 'th' => $text.' | ',
            'tr' => "\n".$text."\n",
            'p', 'div', 'section', 'figure', 'figcaption', 'ul', 'ol', 'table' => "\n\n".trim($text)."\n\n",
            default => $text,
        };
    }

    private function safeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
            || (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true));
    }

    private function encodeUrl(string $url): string
    {
        return str_replace(['<', '>', ' ', "\n", "\r"], ['%3C', '%3E', '%20', '', ''], $url);
    }

    private function longestBacktickRun(string $text): int
    {
        preg_match_all('/`+/', $text, $matches);

        return max([0, ...array_map('strlen', $matches[0])]);
    }
}
