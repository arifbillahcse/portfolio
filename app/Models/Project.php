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
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const VIDEO_URL_PATTERN = '~\Ahttps://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)[A-Za-z0-9_-]{11}\z~D';

    protected function casts(): array
    {
        return [
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
