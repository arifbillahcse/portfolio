<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Rules\CanonicalUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlogPublisher
{
    public function __construct(private PublishingMedia $media) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{publication_id: string, post_id: int, title: string, status: string, public_url: ?string, admin_url: string, published_at: ?string, replayed: bool}
     */
    public function publish(array $attributes): array
    {
        $attributes = array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $attributes);
        if (is_string($attributes['publication_id'] ?? null)) {
            $attributes['publication_id'] = strtolower($attributes['publication_id']);
        }
        $data = Validator::validate($attributes, [
            'publication_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D'],
            'category_slug' => ['required', 'string', Rule::exists('categories', 'slug')->where('type', 'post')],
            'excerpt' => ['required', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:100000'],
            'featured_image_id' => ['required', 'string', 'max:70'],
            'featured_image_alt' => ['required', 'string', 'max:255'],
            'featured_image_caption' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_focus_keyword' => ['nullable', 'string', 'max:255'],
            'seo_related_keywords' => ['nullable', 'array', 'max:15'],
            'seo_related_keywords.*' => ['string', 'max:80'],
            'seo_canonical_url' => ['nullable', 'string', 'max:2048', new CanonicalUrl],
            'seo_robots' => ['nullable', Rule::in(array_keys(SeoMetadata::ROBOTS))],
            'seo_social_title' => ['nullable', 'string', 'max:255'],
            'seo_social_description' => ['nullable', 'string', 'max:320'],
            'seo_schema_type' => ['nullable', Rule::in(['BlogPosting', 'Article', 'TechArticle', 'none'])],
        ]);
        $imagePath = $this->media->path($data['featured_image_id']);
        $payload = array_replace([
            'featured_image_caption' => null, 'seo_title' => null, 'seo_description' => null,
            'seo_focus_keyword' => null, 'seo_related_keywords' => [], 'seo_canonical_url' => null,
            'seo_robots' => 'index_follow', 'seo_social_title' => null, 'seo_social_description' => null,
            'seo_schema_type' => 'BlogPosting',
        ], $data);
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $existing = Post::query()->where('mcp_publication_id', $payload['publication_id'])->first();
        if ($existing) {
            return $this->replay($existing, $hash);
        }
        if (Post::query()->where('slug', $payload['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'This slug already belongs to another post. Existing content was preserved.']);
        }
        $postAttributes = Arr::only($payload, ['title', 'slug', 'excerpt', 'body', ...SeoMetadata::FIELDS]);
        $postAttributes += [
            'category_id' => Category::query()->where('type', 'post')->where('slug', $payload['category_slug'])->sole()->id,
            'cover_image' => $imagePath, 'cover_image_alt' => $payload['featured_image_alt'],
            'cover_image_caption' => $payload['featured_image_caption'],
            'seo_social_image' => $imagePath, 'seo_social_image_alt' => $payload['featured_image_alt'],
            'reading_minutes' => min(120, max(1, (int) ceil(ContentAnalysis::words(strip_tags(PublicContent::markdown($payload['body']))) / 200))),
            'is_published' => true, 'published_at' => now(), 'is_demo' => false, 'is_featured' => false,
            'mcp_publication_id' => $payload['publication_id'], 'mcp_publication_hash' => $hash,
        ];
        try {
            $post = DB::transaction(fn (): Post => tap((new Post)->forceFill($postAttributes), fn (Post $post): bool => $post->save()));
        } catch (UniqueConstraintViolationException) {
            $existing = Post::query()->where('mcp_publication_id', $payload['publication_id'])->first();
            if ($existing) {
                return $this->replay($existing, $hash);
            }
            throw ValidationException::withMessages(['slug' => 'Another request already published this slug. Existing content was preserved.']);
        }

        return [...$this->describe($post), 'replayed' => false];
    }

    /** @return array{publication_id: string, status: string, post_id?: int, title?: string, public_url?: ?string, admin_url?: string, published_at?: ?string} */
    public function status(string $publicationId): array
    {
        Validator::validate(['publication_id' => $publicationId], ['publication_id' => ['required', 'uuid']]);
        $post = Post::query()->where('mcp_publication_id', strtolower($publicationId))->first();

        return $post ? $this->describe($post) : ['publication_id' => strtolower($publicationId), 'status' => 'not_found'];
    }

    /** @return array{publication_id: string, post_id: int, title: string, status: string, public_url: ?string, admin_url: string, published_at: ?string, replayed: bool} */
    private function replay(Post $post, string $hash): array
    {
        if (! hash_equals($post->mcp_publication_hash, $hash)) {
            throw ValidationException::withMessages(['publication_id' => 'This publication_id has already been used with different content. The existing post was not changed.']);
        }

        return [...$this->describe($post), 'replayed' => true];
    }

    /** @return array{publication_id: string, post_id: int, title: string, status: string, public_url: ?string, admin_url: string, published_at: ?string} */
    private function describe(Post $post): array
    {
        $published = $post->is_published && ! $post->is_demo && (! $post->published_at || ! $post->published_at->isFuture());

        return [
            'publication_id' => $post->mcp_publication_id, 'post_id' => $post->id, 'title' => $post->title,
            'status' => $published ? 'published' : 'unpublished',
            'public_url' => $published ? PublicSeo::url('blog.show', ['slug' => $post->slug]) : null,
            'admin_url' => route('filament.admin.resources.posts.edit', ['record' => $post->id]),
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }
}
