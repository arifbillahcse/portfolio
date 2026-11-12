<?php

namespace App\Mcp\Tools;

use App\Support\BlogPublisher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('publish_post')]
#[Description('Immediately publish a NEW public blog post when the user asks to publish. Requires complete Markdown content, a listed category, a previously uploaded image_id and descriptive image alt text. There is no draft or review stage. Reuse the same UUID publication_id and identical content on retries; check get_publication_status after a timeout. Never overwrite an existing post or bypass a conflict by changing the UUID.')]
#[IsDestructive(false)]
#[IsIdempotent]
class PublishPost extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, BlogPublisher $publisher): Response
    {
        return Response::json($publisher->publish($request->all()));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'publication_id' => $schema->string()->description('UUID created once for this article. Reuse it and the same content on retries.')->format('uuid')->required(),
            'title' => $schema->string()->max(255)->required(),
            'slug' => $schema->string()->description('Unique lowercase URL slug using words separated by hyphens.')->max(255)->required(),
            'category_slug' => $schema->string()->description('An existing blog category slug from list_blog_categories.')->required(),
            'excerpt' => $schema->string()->max(1000)->required(),
            'body' => $schema->string()->description('Complete Markdown article. Start sections with H2; the template supplies the H1.')->max(100000)->required(),
            'featured_image_id' => $schema->string()->description('The image_id returned by upload_post_image or the authenticated image upload endpoint.')->required(),
            'featured_image_alt' => $schema->string()->max(255)->required(),
            'featured_image_caption' => $schema->string()->max(1000),
            'seo_title' => $schema->string()->max(255),
            'seo_description' => $schema->string()->max(320),
            'seo_focus_keyword' => $schema->string()->max(255),
            'seo_related_keywords' => $schema->array()->items($schema->string()->max(80))->max(15),
            'seo_social_title' => $schema->string()->max(255),
            'seo_social_description' => $schema->string()->max(320),
            'seo_canonical_url' => $schema->string()->description('Normally omit. Absolute HTTP(S) preferred URL without credentials or fragments.')->max(2048),
            'seo_robots' => $schema->string()->enum(['index_follow', 'index_nofollow', 'noindex_follow', 'noindex_nofollow']),
            'seo_schema_type' => $schema->string()->enum(['BlogPosting', 'Article', 'TechArticle', 'none']),
        ];
    }
}
