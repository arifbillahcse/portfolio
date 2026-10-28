<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id',
    'title',
    'slug',
    'excerpt',
    'body',
    'cover_image',
    'cover_image_alt',
    'cover_image_caption',
    'body_image_metadata',
    'seo_schema_type',
    'gallery_metadata',
    'video_schema_enabled',
    'video_name',
    'video_description',
    'video_thumbnail',
    'video_uploaded_at',
    'video_duration_seconds',
    'gallery',
    'external_url',
    'repository_url',
    'video_url',
    'tools',
    'is_featured',
    'is_demo',
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
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const VIDEO_URL_PATTERN = '~\Ahttps://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)[A-Za-z0-9_-]{11}\z~D';

    protected function casts(): array
    {
        return [
            'body_image_metadata' => 'array',
            'gallery_metadata' => 'array',
            'video_schema_enabled' => 'boolean',
            'video_uploaded_at' => 'datetime',
            'video_duration_seconds' => 'integer',
            'seo_related_keywords' => 'array',
            'gallery' => 'array',
            'catalogue_metadata' => 'array',
            'tools' => 'array',
            'is_featured' => 'boolean',
            'is_demo' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
