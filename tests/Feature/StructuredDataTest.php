<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function graph(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new DOMXPath($document))->query('//script[@type="application/ld+json"]');
        $this->assertCount(1, $nodes);

        return json_decode($nodes->item(0)->textContent, true, 64, JSON_THROW_ON_ERROR)['@graph'];
    }

    private function node(array $graph, string $type): array
    {
        $found = array_values(array_filter($graph, fn (array $node): bool => $node['@type'] === $type));
        $this->assertCount(1, $found);

        return $found[0];
    }

    public function test_about_identifies_the_visible_person_with_a_profile_page_and_stable_author_identity(): void
    {
        config(['app.url' => 'https://arif.example']);
        $profile = Profile::factory()->create(['name' => 'Arif Billah', 'headline' => 'Development and cloud', 'portrait_alt' => 'Arif wearing a formal suit']);
        SiteSetting::factory()->create(['facebook_url' => 'https://facebook.com/arifbillahcse', 'linkedin_url' => 'javascript:alert(1)']);

        $html = $this->get('/about?utm_source=example')->assertOk()->getContent();
        $graph = $this->graph($html);
        $person = $this->node($graph, 'Person');
        $page = $this->node($graph, 'ProfilePage');
        $this->assertSame('https://arif.example/#person', $person['@id']);
        $this->assertSame('Arif Billah', $person['name']);
        $this->assertSame(['https://facebook.com/arifbillahcse'], $person['sameAs']);
        $this->assertSame(['@id' => $person['@id']], $page['mainEntity']);
        $this->assertSame('https://arif.example/about', $page['url']);
        $this->assertStringContainsString('Arif wearing a formal suit', $html);
        $this->assertArrayNotHasKey('aggregateRating', $person);
        $this->assertArrayNotHasKey('address', $person);
        $this->assertArrayNotHasKey('dateCreated', $page);
    }

    public static function pageTypes(): array
    {
        return [['/', 'WebPage'], ['/contact', 'ContactPage'], ['/services', 'CollectionPage'], ['/projects', 'CollectionPage'], ['/blog', 'CollectionPage']];
    }

    #[DataProvider('pageTypes')]
    public function test_main_pages_have_the_correct_page_type_and_site_relationship(string $path, string $type): void
    {
        $graph = $this->graph($this->get($path)->assertOk()->getContent());
        $page = $this->node($graph, $type);
        $this->assertSame(['@id' => rtrim(config('app.url'), '/').'/#website'], $page['isPartOf']);
    }

    public function test_article_uses_its_visible_cover_and_author_without_substituting_a_social_image(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('cover.png', 640, 360)->storeAs('posts', 'cover.png', 'public');
        $post = Post::factory()->published()->create([
            'published_at' => '2025-01-01 12:00:00',
            'cover_image' => 'posts/cover.png', 'cover_image_alt' => 'Laravel deployment dashboard',
            'cover_image_caption' => 'Deployment status in the administration panel',
            'seo_social_image' => 'profile/seo/share.png', 'seo_schema_type' => 'TechArticle',
        ]);

        $graph = $this->graph($this->get('/blog/'.$post->slug)->assertOk()->getContent());
        $article = $this->node($graph, 'TechArticle');
        $this->assertSame($post->title, $article['headline']);
        $this->assertStringEndsWith('/storage/posts/cover.png', $article['image']['contentUrl']);
        $this->assertSame(640, $article['image']['width']);
        $this->assertSame(360, $article['image']['height']);
        $this->assertSame('Laravel deployment dashboard', $article['image']['description']);
        $this->assertSame('Deployment status in the administration panel', $article['image']['caption']);
        $this->assertSame($this->node($graph, 'Person')['@id'], $article['author']['@id']);
        $this->assertSame($post->published_at->toIso8601String(), $article['datePublished']);
    }

    public function test_article_without_a_cover_does_not_claim_the_portrait_as_its_article_image(): void
    {
        $post = Post::factory()->published()->create(['cover_image' => null, 'published_at' => null]);
        $article = $this->node($this->graph($this->get('/blog/'.$post->slug)->assertOk()->getContent()), 'BlogPosting');
        $this->assertArrayNotHasKey('image', $article);
        $this->assertArrayNotHasKey('datePublished', $article);
    }

    public function test_services_and_projects_describe_the_visible_work_and_only_the_profile_as_provider(): void
    {
        $service = Service::factory()->published()->create(['summary' => 'Managed Linux setup', 'seo_description' => 'Search description']);
        $serviceNode = $this->node($this->graph($this->get('/services/'.$service->slug)->assertOk()->getContent()), 'Service');
        $this->assertSame('Managed Linux setup', $serviceNode['description']);
        $this->assertSame('Person', $serviceNode['provider']['@type']);
        $project = Project::factory()->published()->create();
        $work = $this->node($this->graph($this->get('/projects/'.$project->slug)->assertOk()->getContent()), 'CreativeWork');
        $this->assertSame($project->title, $work['name']);
        $this->assertArrayNotHasKey('review', $work);
    }

    public function test_video_markup_requires_verified_metadata_and_matches_the_visible_embedded_video(): void
    {
        Http::preventStrayRequests();
        Storage::fake('public');
        $this->freezeTime();
        UploadedFile::fake()->image('thumbnail.jpg', 1280, 720)->storeAs('projects/video', 'thumbnail.jpg', 'public');
        $project = Project::factory()->published()->create([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_schema_enabled' => true,
            'video_name' => 'Cloud deployment walkthrough', 'video_description' => 'A demonstration of the deployed application.',
            'video_thumbnail' => 'projects/video/thumbnail.jpg', 'video_uploaded_at' => now()->subDays(20),
            'video_duration_seconds' => 125,
        ]);

        $response = $this->get('/projects/'.$project->slug)->assertOk()
            ->assertSeeText('Cloud deployment walkthrough')->assertSeeText('A demonstration of the deployed application.')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', false);
        $graph = $this->graph($response->getContent());
        $video = $this->node($graph, 'VideoObject');
        $this->assertSame('PT125S', $video['duration']);
        $this->assertSame($project->video_uploaded_at->toIso8601String(), $video['uploadDate']);
        $this->assertStringEndsWith('/storage/projects/video/thumbnail.jpg', $video['thumbnailUrl'][0]);
        $this->assertSame(['@id' => $video['@id']], $this->node($graph, 'CreativeWork')['video']);
        Http::assertNothingSent();
    }

    public static function incompleteVideos(): array
    {
        return [
            'off' => [['video_schema_enabled' => false]],
            'name missing' => [['video_name' => null]],
            'description missing' => [['video_description' => null]],
            'date missing' => [['video_uploaded_at' => null]],
            'future date' => [['video_uploaded_at' => '2099-01-01']],
            'thumbnail missing' => [['video_thumbnail' => null]],
            'thumbnail file missing' => [['video_thumbnail' => 'projects/video/missing.jpg']],
            'unsafe thumbnail' => [['video_thumbnail' => '../private.jpg']],
            'invalid video' => [['video_url' => 'https://example.com/watch']],
        ];
    }

    #[DataProvider('incompleteVideos')]
    public function test_incomplete_or_unverified_video_metadata_does_not_emit_video_schema(array $override): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('thumbnail.jpg', 320, 180)->storeAs('projects/video', 'thumbnail.jpg', 'public');
        $project = Project::factory()->published()->create(array_replace([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_schema_enabled' => true,
            'video_name' => 'Deployment video', 'video_description' => 'Actual deployment',
            'video_thumbnail' => 'projects/video/thumbnail.jpg', 'video_uploaded_at' => '2025-01-01',
        ], $override));

        $graph = $this->graph($this->get('/projects/'.$project->slug)->assertOk()->getContent());
        $this->assertNotContains('VideoObject', array_column($graph, '@type'));
    }

    public function test_schema_can_be_disabled_per_page_without_disabling_canonical_or_changing_robots(): void
    {
        config(['portfolio.indexable' => true]);
        SiteSetting::factory()->create(['page_seo' => ['about' => ['seo_schema_type' => 'none']]]);
        $this->get('/about')->assertOk()->assertDontSee('application/ld+json', false)->assertSee('rel="canonical"', false);
        $post = Post::factory()->published()->create(['seo_schema_type' => 'none']);
        $this->get('/blog/'.$post->slug)->assertOk()->assertDontSee('application/ld+json', false)->assertSee('rel="canonical"', false);
    }

    public function test_samples_and_private_previews_do_not_emit_work_or_video_claims(): void
    {
        $project = Project::factory()->published()->create(['is_demo' => true]);
        $graph = $this->graph($this->get('/projects/'.$project->slug)->assertOk()->getContent());
        $this->assertNotContains('CreativeWork', array_column($graph, '@type'));
        $this->assertNotContains('VideoObject', array_column($graph, '@type'));
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('projects.preview', $project))->assertOk()->assertDontSee('application/ld+json', false);
    }

    public function test_admins_can_save_schema_selection_and_reject_wrong_content_types(): void
    {
        $post = Post::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditPost::class, ['record' => $post->id])->fillForm(['seo_schema_type' => 'TechArticle'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('TechArticle', $post->fresh()->seo_schema_type);
        Livewire::test(EditPost::class, ['record' => $post->id])->fillForm(['seo_schema_type' => 'Product'])->call('save')->assertHasFormErrors(['seo_schema_type']);
        Livewire::test(SeoSettings::class)->fillForm(['page_seo.about.seo_schema_type' => 'none'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('none', SiteSetting::query()->where('key', 'main')->sole()->page_seo['about']['seo_schema_type']);
    }

    public function test_video_editor_requires_real_metadata_before_enabling_schema(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditProject::class, ['record' => $project->id])
            ->fillForm(['video_schema_enabled' => true])->call('save')
            ->assertHasFormErrors(['video_url', 'video_name', 'video_description', 'video_thumbnail', 'video_uploaded_at']);
        $this->assertFalse($project->fresh()->video_schema_enabled);
    }

    public function test_verified_video_metadata_can_be_saved_without_publishing_and_future_dates_are_rejected(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('video.jpg', 1280, 720)->storeAs('projects/video', 'video.jpg', 'public');
        $project = Project::factory()->create(['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_thumbnail' => 'projects/video/video.jpg']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'video_schema_enabled' => true, 'video_name' => 'Linux deployment walkthrough',
            'video_description' => 'The deployment process and server setup.', 'video_uploaded_at' => '2025-01-01 12:00:00',
            'video_duration_seconds' => 90,
        ])->call('save')->assertHasNoFormErrors();
        $project->refresh();
        $this->assertTrue($project->video_schema_enabled);
        $this->assertSame('Linux deployment walkthrough', $project->video_name);
        $this->assertSame(90, $project->video_duration_seconds);
        $this->assertFalse($project->is_published);
        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm(['video_uploaded_at' => '2099-01-01 12:00:00'])
            ->call('save')->assertHasFormErrors(['video_uploaded_at']);
        $this->assertSame('2025-01-01 12:00:00', $project->fresh()->video_uploaded_at->format('Y-m-d H:i:s'));
    }
}
