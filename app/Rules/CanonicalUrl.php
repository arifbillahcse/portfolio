<?php

namespace App\Rules;

use App\Support\SeoMetadata;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CanonicalUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SeoMetadata::validCanonical($value)) {
            $fail('Use an absolute HTTP or HTTPS canonical URL without credentials, spaces or a fragment.');
        }
    }
}
