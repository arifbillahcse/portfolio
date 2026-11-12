<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_blog_categories')]
#[Description('List existing blog category names and slugs. Choose a returned slug when publishing. Does not list or change project categories.')]
#[IsReadOnly]
class ListBlogCategories extends Tool
{
    public function handle(): Response
    {
        return Response::json(['categories' => Category::query()->where('type', 'post')->orderBy('sort_order')->orderBy('name')->get(['name', 'slug'])->toArray()]);
    }
}
