<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'group',
    'summary',
    'body',
    'body_image_metadata',
    'seo_schema_type',
    'offerings',
    'icon',
    'is_published',
    'published_at',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_focus_keyword',
    'seo_related_keywords',
    'seo_canonical_url',
    'seo_robots',
    'seo_social_title',
    'seo_social_description',
    'seo_social_image',
    'seo_social_image_alt',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'body_image_metadata' => 'array',
            'seo_related_keywords' => 'array',
            'offerings' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
