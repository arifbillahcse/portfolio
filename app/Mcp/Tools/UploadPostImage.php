<?php

namespace App\Mcp\Tools;

use App\Support\PublishingMedia;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('upload_post_image')]
#[Description('Store a real featured image up to 4 MB. Supply exactly one of image_url (HTTPS on a server-approved host) or image_base64 (real bytes, no data URL prefix). Never invent base64 or local file paths. Remote tools cannot read chat attachments or local files by filename. For local files use the authenticated multipart upload endpoint /api/integrations/blog/images. Returns image_id for publish_post. Does not publish a post.')]
#[IsDestructive(false)]
#[IsIdempotent]
class UploadPostImage extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, PublishingMedia $media): Response
    {
        $data = $request->validate([
            'image_url' => ['nullable', 'required_without:image_base64', 'prohibits:image_base64', 'string', 'max:8192'],
            'image_base64' => ['nullable', 'required_without:image_url', 'prohibits:image_url', 'string', 'max:5592408'],
        ]);

        return Response::json(filled($data['image_url'] ?? null) ? $media->fromUrl($data['image_url']) : $media->fromBase64($data['image_base64']));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'image_url' => $schema->string()->description('HTTPS URL of a real image on an explicitly approved hostname. Redirects are not followed.')->max(8192),
            'image_base64' => $schema->string()->description('Actual base64 image bytes, obtained by a tool with access to the file; never fabricate or reconstruct from visual inspection.')->max(5592408),
        ];
    }
}
