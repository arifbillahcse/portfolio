<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetPublicationStatus;
use App\Mcp\Tools\ListBlogCategories;
use App\Mcp\Tools\PublishPost;
use App\Mcp\Tools\UploadPostImage;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Arif Blog Publisher')]
#[Version('1.0.0')]
#[Instructions('Publish directly to the live blog only when the user requests publication. There is no draft workflow. List categories first. Upload a real image and use its returned image_id; never invent image data, file IDs, facts, or sources. Remote tools cannot read local computer paths or automatically access chat attachments. Image uploads accept real base64 data or an HTTPS URL on a server-approved host; files may also be uploaded to the authenticated upload endpoint. Prepare a complete Markdown article, excerpt, image alt text, and SEO fields. Generate a UUID publication_id once for each article and reuse that same UUID and payload on retries. After an uncertain result, call get_publication_status before retrying. Never generate a new UUID to bypass a conflict. Publication is immediate and public. Return the public_url and distinguish published from indexed. Existing posts cannot be edited or deleted with these tools.')]
class BlogServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListBlogCategories::class,
        UploadPostImage::class,
        PublishPost::class,
        GetPublicationStatus::class,
    ];
}
