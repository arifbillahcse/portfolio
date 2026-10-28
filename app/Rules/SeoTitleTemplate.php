<?php

namespace App\Rules;

use App\Support\SeoMetadata;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SeoTitleTemplate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SeoMetadata::validTemplate($value)) {
            $fail('Include {title}; only {title}, {brand} and {name} tokens are supported. HTML and line breaks are not allowed.');
        }
    }
}
