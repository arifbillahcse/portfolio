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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_publication_status')]
#[Description('Check a publication_id after a timeout or uncertain publishing result. Returns published, unpublished, or not_found and the current public URL. Never republishes or changes a post.')]
#[IsReadOnly]
class GetPublicationStatus extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, BlogPublisher $publisher): Response
    {
        $data = $request->validate(['publication_id' => ['required', 'uuid']]);

        return Response::json($publisher->status($data['publication_id']));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'publication_id' => $schema->string()->description('The original UUID used for this article.')->format('uuid')->required(),
        ];
    }
}
