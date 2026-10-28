<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use Database\Seeders\PortfolioContentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PortfolioSeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_content_is_seeded_without_publishing_samples(): void
    {
        $this->seed(PortfolioContentSeeder::class);

        $this->assertDatabaseCount('services', 20);
        $this->assertDatabaseCount('projects', 6);
        $this->assertDatabaseCount('posts', 8);
        $this->assertDatabaseCount('categories', 7);
        $this->assertDatabaseCount('profiles', 1);
        $this->assertDatabaseCount('site_settings', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, Project::query()->published()->count());
        $this->assertSame(0, Post::query()->published()->count());
        $this->assertSame(20, Service::query()->published()->count());
        $this->assertSame('emran.alarif8@gmail.com', SiteSetting::query()->sole()->contact_email);
        $this->assertSame('Arif Billah', Profile::query()->sole()->name);
    }

    public function test_running_the_seeder_again_preserves_admin_edits(): void
    {
        $this->seed(PortfolioContentSeeder::class);
        $service = Service::query()->firstOrFail();
        $service->update(['title' => 'My edited service', 'is_published' => false]);

        $this->seed(PortfolioContentSeeder::class);

        $this->assertDatabaseCount('services', 20);
        $this->assertDatabaseCount('projects', 6);
        $this->assertDatabaseCount('posts', 8);
        $this->assertSame('My edited service', $service->fresh()->title);
        $this->assertFalse($service->fresh()->is_published);
    }
}
