<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishedContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function contentModels(): array
    {
        return [[Project::class], [Post::class], [Service::class]];
    }

    #[DataProvider('contentModels')]
    public function test_published_scope_excludes_drafts_and_future_content(string $model): void
    {
        $this->freezeTime();
        $immediate = $model::factory()->published()->create();
        $past = $model::factory()->published()->create(['published_at' => now()->subDay()]);
        $model::factory()->published()->create(['published_at' => now()->addDay()]);
        $model::factory()->create(['published_at' => now()->subDay()]);

        $ids = $model::query()->published()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$immediate->id, $past->id], $ids);
    }
}
