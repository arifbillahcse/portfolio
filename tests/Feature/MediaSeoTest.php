<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Support\MediaSeo;
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

class MediaSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_cover_alt_caption_intrinsic_dimensions_and_social_dimensions_use_the_actual_image(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('cover.png', 800, 450)->storeAs('posts', 'cover.png', 'public');
        $post = Post::factory()->published()->create(['is_featured' => true, 'cover_image' => 'posts/cover.png', 'cover_image_alt' => 'Application settings dashboard', 'cover_image_caption' => 'Settings shown during deployment']);

        $response = $this->get('/blog/'.$post->slug)->assertOk()->assertSeeText('Settings shown during deployment');
        $xpath = $this->xpath($response->getContent());
        $image = $xpath->query('//img[contains(@class, "detail-cover")]')->item(0);
        $this->assertSame('Application settings dashboard', $image->getAttribute('alt'));
        $this->assertSame('800', $image->getAttribute('width'));
        $this->assertSame('450', $image->getAttribute('height'));
        $this->assertSame('eager', $image->getAttribute('loading'));
        $this->assertSame('800', $xpath->query('//meta[@property="og:image:width"]')->item(0)->getAttribute('content'));
        $this->assertSame('Application settings dashboard', $xpath->query('//meta[@property="og:image:alt"]')->item(0)->getAttribute('content'));
        $featured = $this->xpath($this->get('/blog')->assertOk()->getContent())->query('//div[contains(@class, "featured-art")]/img')->item(0);
        $this->assertSame('Application settings dashboard', $featured->getAttribute('alt'));
    }

    public function test_gallery_descriptions_follow_the_image_path_after_reordering_and_escape_captions(): void
    {
        Storage::fake('public');
        $project = Project::factory()->published()->create([
            'gallery' => ['projects/gallery/second.png', 'projects/gallery/first.png'],
            'gallery_metadata' => [
                ['src' => 'projects/gallery/first.png', 'alt' => 'Server monitoring dashboard', 'caption' => '<script>alert("caption")</script>'],
                ['src' => 'projects/gallery/second.png', 'alt' => 'Application sign-in screen'],
            ],
        ]);
        $response = $this->get('/projects/'.$project->slug)->assertOk()->assertDontSee('<script>alert("caption")</script>', false);
        $images = $this->xpath($response->getContent())->query('//div[@class="detail-gallery"]//img');
        $this->assertSame('Application sign-in screen', $images->item(0)->getAttribute('alt'));
        $this->assertSame('Server monitoring dashboard', $images->item(1)->getAttribute('alt'));
        $this->assertStringContainsString('&lt;script&gt;', $response->getContent());
    }

    public function test_inline_overrides_preserve_markdown_and_existing_alt_text_and_mark_decorative_images(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('screen.png', 500, 300)->storeAs('posts/content', 'screen.png', 'public');
        $body = "## Deployment\n\n![](/storage/posts/content/screen.png)\n\n![Existing useful description](https://images.example/keep.png)\n\n![](/storage/posts/content/divider.png)";
        $post = Post::factory()->published()->create(['body' => $body, 'body_image_metadata' => [
            ['src' => '/storage/posts/content/screen.png', 'alt' => 'SSL status in the server dashboard'],
            ['src' => '/storage/posts/content/divider.png', 'decorative' => true],
        ]]);

        $response = $this->get('/blog/'.$post->slug)->assertOk();
        $images = $this->xpath($response->getContent())->query('//div[@class="prose"]//img');
        $this->assertSame('SSL status in the server dashboard', $images->item(0)->getAttribute('alt'));
        $this->assertSame('500', $images->item(0)->getAttribute('width'));
        $this->assertSame('Existing useful description', $images->item(1)->getAttribute('alt'));
        $this->assertSame('', $images->item(2)->getAttribute('alt'));
        $this->assertSame('presentation', $images->item(2)->getAttribute('role'));
        $this->assertSame('lazy', $images->item(0)->getAttribute('loading'));
        $this->assertSame($body, $post->fresh()->body);
    }

    public function test_inline_alt_overrides_cannot_inject_attributes_or_html_and_raw_html_remains_stripped(): void
    {
        $post = Post::factory()->published()->create(['body' => '![](/storage/posts/safe.png)'."\n\n".'<script>alert("raw")</script>', 'body_image_metadata' => [
            ['src' => '/storage/posts/safe.png', 'alt' => '" onerror="alert(1)"><script>alert("alt")</script>'],
        ]]);
        $response = $this->get('/blog/'.$post->slug)->assertOk()->assertDontSee('<script>alert("raw")</script>', false)->assertDontSee('<script>alert("alt")</script>', false);
        $image = $this->xpath($response->getContent())->query('//div[@class="prose"]//img')->item(0);
        $this->assertFalse($image->hasAttribute('onerror'));
        $this->assertStringContainsString('onerror=', $image->getAttribute('alt'));
    }

    public static function untrustedSources(): array
    {
        return [
            ['https://external.example/storage/posts/test.png'],
            ['http://127.0.0.1/private.png'],
            ['file:///etc/passwd'],
            ['/storage/posts/../../private.png'],
            ['/storage/posts/%2e%2e/private.png'],
            ['/storage/posts/test.svg'],
            ['/storage/posts/missing.png'],
        ];
    }

    #[DataProvider('untrustedSources')]
    public function test_dimension_inspection_does_not_fetch_remote_or_unsafe_files_or_invent_sizes(string $source): void
    {
        Http::preventStrayRequests();
        $this->assertNull(app(MediaSeo::class)->size($source));
        Http::assertNothingSent();
    }

    public function test_admins_can_save_cover_and_inline_descriptions_without_changing_the_body_or_publication(): void
    {
        $post = Post::factory()->create(['body' => '![](/storage/posts/screen.png)']);
        $before = $post->body;
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditPost::class, ['record' => $post->id])->fillForm([
            'cover_image_alt' => 'Custom application interface', 'cover_image_caption' => 'Application dashboard',
            'body_image_metadata' => [['src' => '/storage/posts/screen.png', 'alt' => 'Server settings']],
        ])->call('save')->assertHasNoFormErrors();

        $post->refresh();
        $this->assertSame('Custom application interface', $post->cover_image_alt);
        $this->assertSame('Server settings', $post->body_image_metadata[0]['alt']);
        $this->assertSame($before, $post->body);
        $this->assertFalse($post->is_published);
    }

    public function test_inline_metadata_editor_rejects_unsafe_sources_and_requires_a_description_for_informative_images(): void
    {
        $post = Post::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditPost::class, ['record' => $post->id])->fillForm([
            'body_image_metadata' => [['src' => 'javascript:alert(1)', 'alt' => '', 'decorative' => false]],
        ])->call('save')->assertHasFormErrors(['body_image_metadata.0.src', 'body_image_metadata.0.alt']);
        $this->assertNull($post->fresh()->body_image_metadata);
    }

    public function test_gallery_editor_saves_descriptions_for_selected_uploaded_images(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('gallery.png', 800, 600)->storeAs('projects/gallery', 'gallery.png', 'public');
        $project = Project::factory()->create(['gallery' => ['projects/gallery/gallery.png']]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'gallery_metadata' => [['src' => 'projects/gallery/gallery.png', 'alt' => 'Linux monitoring dashboard', 'caption' => 'CPU and memory monitoring']],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Linux monitoring dashboard', $project->fresh()->gallery_metadata[0]['alt']);
    }

    public function test_media_audit_reports_missing_descriptions_and_video_metadata_without_modifying_records(): void
    {
        Storage::fake('public');
        $post = Post::factory()->published()->create(['body' => '![](/storage/posts/content/screen.png)', 'cover_image' => 'posts/cover.png']);
        $before = $post->fresh()->getRawOriginal();
        Project::factory()->published()->create(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->artisan('portfolio:seo-media-audit')->expectsOutputToContain('Missing description:')
            ->expectsOutputToContain('Video schema is off')->expectsOutputToContain('No content or files changed.')->assertSuccessful();
        $this->assertSame($before, $post->fresh()->getRawOriginal());
    }

    public function test_image_sitemap_lists_existing_visible_images_once_and_excludes_private_or_missing_media(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://arif.example']);
        Storage::fake('public');
        UploadedFile::fake()->image('cover.png', 800, 600)->storeAs('posts', 'cover.png', 'public');
        UploadedFile::fake()->image('screen.png', 640, 360)->storeAs('posts/content', 'screen.png', 'public');
        Post::factory()->published()->create([
            'slug' => 'published', 'cover_image' => 'posts/cover.png',
            'body' => '![](/storage/posts/content/screen.png)'."\n\n".'![](/storage/posts/cover.png)'."\n\n".'![](https://external.example/screen.png)',
        ]);
        Post::factory()->create(['slug' => 'draft', 'cover_image' => 'posts/cover.png']);
        Post::factory()->published()->create(['slug' => 'noindex', 'seo_robots' => 'noindex_follow', 'cover_image' => 'posts/cover.png']);
        Post::factory()->published()->create(['slug' => 'missing', 'cover_image' => 'posts/missing.png']);
        $response = $this->get('/sitemaps/blog/1.xml')->assertOk()->assertDontSee('/blog/draft', false)
            ->assertDontSee('/blog/noindex', false)->assertDontSee('external.example', false)->assertDontSee('posts/missing.png', false);
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($response->getContent()));
        $images = $document->getElementsByTagNameNS('http://www.google.com/schemas/sitemap-image/1.1', 'loc');
        $this->assertCount(2, $images);
        $this->assertSame('https://arif.example/storage/posts/cover.png', $images->item(0)->textContent);
        $this->assertSame('https://arif.example/storage/posts/content/screen.png', $images->item(1)->textContent);
    }
}
