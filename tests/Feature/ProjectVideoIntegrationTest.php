<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectVideoIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    /** @return array<string, array{string}> */
    public static function supportedVideoLinks(): array
    {
        return [
            'watch URL' => ['https://www.youtube.com/watch?v=FU4Zd3GTgYs'],
            'short URL' => ['https://youtu.be/FU4Zd3GTgYs'],
            'without www' => ['https://youtube.com/watch?v=FU4Zd3GTgYs'],
        ];
    }

    #[DataProvider('supportedVideoLinks')]
    public function test_a_published_video_project_has_a_responsive_safe_player_and_youtube_fallback(string $url): void
    {
        $project = Project::factory()->published()->create([
            'title' => 'WordPress <migration> "guide"', 'video_url' => $url, 'cover_image' => 'projects/thumbnail.jpg',
        ]);

        $html = $this->get(route('projects.show', $project->slug))->assertOk()->getContent();

        $xpath = $this->document($html);
        $iframes = $xpath->query('//section[@id="project-video"]//iframe');
        $this->assertCount(1, $iframes);
        $player = $iframes->item(0);
        $this->assertSame('https://www.youtube-nocookie.com/embed/FU4Zd3GTgYs?rel=0', $player->getAttribute('src'));
        $this->assertSame('Video demonstration: WordPress <migration> "guide"', $player->getAttribute('title'));
        $this->assertSame('lazy', $player->getAttribute('loading'));
        $this->assertSame('strict-origin-when-cross-origin', $player->getAttribute('referrerpolicy'));
        $this->assertStringNotContainsString('autoplay', $player->getAttribute('src'));
        $this->assertCount(1, $xpath->query('//section[@id="project-video"]//a[@href="https://www.youtube.com/watch?v=FU4Zd3GTgYs"]'));
        $this->assertCount(0, $xpath->query('//img[@class="detail-cover"]'));
        $this->assertCount(1, $xpath->query('//h1'));
        $this->assertCount(0, $xpath->query('//migration'));
    }

    public function test_home_and_projects_cards_link_to_the_published_video_section(): void
    {
        $project = Project::factory()->published()->create(['slug' => 'video-project', 'video_url' => 'https://youtu.be/FU4Zd3GTgYs']);
        Project::factory()->published()->create(['slug' => 'no-video']);
        Project::factory()->create(['slug' => 'private-video', 'video_url' => 'https://youtu.be/YYKVGRjWGFI']);

        foreach (['/', '/projects'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertSee(route('projects.show', $project->slug).'#project-video', false)
                ->assertDontSee('/projects/no-video#project-video', false)->assertDontSee('/projects/private-video', false);
        }
    }

    /** @return array<string, array{?string}> */
    public static function invalidVideoLinks(): array
    {
        return [
            'no video' => [null],
            'arbitrary host' => ['https://attacker.invalid/FU4Zd3GTgYs'],
            'youtube hostname lookalike' => ['https://www.youtube.com.attacker.invalid/watch?v=FU4Zd3GTgYs'],
            'javascript URL' => ['javascript:alert(1)'],
            'untrusted iframe HTML' => ['<iframe src="https://attacker.invalid"></iframe>'],
            'invalid ID' => ['https://youtu.be/invalid'],
            'insecure URL' => ['http://youtu.be/FU4Zd3GTgYs'],
        ];
    }

    #[DataProvider('invalidVideoLinks')]
    public function test_invalid_video_values_do_not_create_embeds_or_video_links(?string $url): void
    {
        $project = Project::factory()->published()->create(['video_url' => $url, 'cover_image' => 'projects/cover.jpg']);

        $detail = $this->get(route('projects.show', $project->slug))->assertOk();
        $listing = $this->get('/projects')->assertOk();

        $xpath = $this->document($detail->getContent());
        $this->assertCount(0, $xpath->query('//iframe'));
        $this->assertCount(0, $xpath->query('//*[@id="project-video"]'));
        $this->assertCount(1, $xpath->query('//img[@class="detail-cover"]'));
        $listing->assertDontSee('#project-video', false);
    }

    public function test_only_administrators_can_preview_drafts_and_scheduled_projects(): void
    {
        $project = Project::factory()->create(['slug' => 'private-project', 'video_url' => 'https://youtu.be/FU4Zd3GTgYs']);
        $previewUrl = route('projects.preview', $project);

        $this->get($previewUrl)->assertRedirect(route('filament.admin.auth.login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($previewUrl)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($previewUrl)->assertOk()
            ->assertSeeText('Administrator preview')->assertSee('https://www.youtube-nocookie.com/embed/FU4Zd3GTgYs', false);
        $project->update(['is_published' => true, 'published_at' => now()->addDay()]);
        $this->get($previewUrl)->assertOk();
        $this->get(route('projects.show', $project->slug))->assertNotFound();
        $this->assertTrue($project->fresh()->published_at->isFuture());
    }

    public function test_preview_is_private_noindex_has_a_public_canonical_and_does_not_load_chat_widgets(): void
    {
        config(['app.url' => 'https://portfolio.company', 'portfolio.indexable' => true]);
        SiteSetting::factory()->create(['google_site_verification' => 'test-google-token', 'chat_widget_enabled' => true,
            'chat_widget_code' => '<script id="test-chat-widget">window.chat = true;</script>']);
        $project = Project::factory()->create(['slug' => 'private-project']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->get(route('projects.preview', $project))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('rel="canonical" href="https://portfolio.company/projects/private-project"', false)
            ->assertDontSee('test-chat-widget')->assertDontSee('google-site-verification');
        $this->assertFalse($project->fresh()->is_published);
        $this->get('/sitemap.xml')->assertDontSee('/admin/projects/', false);
    }

    public function test_project_edit_page_links_to_previewing_saved_content(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('filament.admin.resources.projects.edit', $project))->assertOk()
            ->assertSeeText('Preview saved project')->assertSee(route('projects.preview', $project), false);
    }

    public function test_preview_rejects_a_session_invalidated_by_a_password_change(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $oldHash = $admin->password;
        $admin->update(['password' => 'A-new-secure-password-123!']);
        $this->actingAs($admin->fresh());

        $this->withSession(['password_hash_web' => $oldHash])->get(route('projects.preview', $project))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_catalogue_projects_integrate_with_preview_pagination_filters_and_seo_without_publishing_real_data(): void
    {
        Storage::fake('public');
        config(['portfolio.indexable' => true, 'app.url' => 'https://portfolio.company']);
        $this->artisan('portfolio:import-catalogue')->assertSuccessful();
        $videoProject = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:9')->sole();
        $unresolved = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:25')->sole();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $preview = $this->get(route('projects.preview', $videoProject))->assertOk();
        $preview->assertSee('https://www.youtube-nocookie.com/embed/FU4Zd3GTgYs', false)->assertDontSee('source_unavailable');
        $this->get(route('projects.preview', $unresolved))->assertOk()->assertDontSee('youtube-nocookie.com')
            ->assertDontSee('_WFtwrC3ylc')->assertDontSee('Candidate title from phase 1');
        $this->assertSame(0, Project::query()->published()->count());

        Project::query()->where('catalogue_key', '!=', 'arif-cloud-portfolio:25')->update(['is_published' => true]);
        $first = $this->get('/projects')->assertOk();
        $first->assertViewHas('projects', fn ($projects): bool => $projects->total() === 46 && $projects->count() === 9)
            ->assertSee('?page=2', false);
        $this->get('/projects?page=6')->assertOk()->assertViewHas('projects', fn ($projects): bool => $projects->count() === 1);
        $this->get('/projects?category=backup-performance-monitoring')->assertOk()
            ->assertSeeText('WordPress Migration to Cloudways')->assertDontSeeText('WordPress Backup and Disaster Recovery');
        $detail = $this->get(route('projects.show', $videoProject->slug))->assertOk();
        $detail->assertSee('rel="canonical" href="https://portfolio.company/projects/wordpress-migration-to-cloudways"', false)
            ->assertSee('property="og:image" content="https://portfolio.company/storage/'.$videoProject->cover_image.'"', false);
        $xpath = $this->document($detail->getContent());
        $node = $xpath->query('//script[@type="application/ld+json"]')->item(0);
        $schema = json_decode($node->textContent, true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame('CreativeWork', $schema['@graph'][2]['@type']);
        $this->assertSame('WordPress Migration to Cloudways', $schema['@graph'][2]['name']);
        $sitemap = $this->get('/sitemaps/projects/1.xml')->assertOk();
        $sitemap->assertSee('/projects/wordpress-migration-to-cloudways', false)
            ->assertDontSee('/projects/wordpress-backup-and-disaster-recovery', false)->assertDontSee('/admin/projects/', false);
    }
}
