<?php

namespace App\Support;

use DOMDocument;

class SiteIntegrations
{
    public const GOOGLE_TOKEN_PATTERN = '~\A[A-Za-z0-9_+/=-]{1,512}\z~D';

    public static function googleVerificationToken(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = trim($value);
        if (! str_starts_with($value, '<') || strlen($value) > 2048) {
            return $value;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<html><head>'.$value.'</head><body></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $tags = $document->getElementsByTagName('meta');
            if ($tags->length === 1) {
                $tag = $tags->item(0);
                if (strtolower($tag->getAttribute('name')) === 'google-site-verification' && trim($tag->getAttribute('content')) !== '') {
                    return trim($tag->getAttribute('content'));
                }
            }

            return $value;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
