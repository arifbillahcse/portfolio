<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Models\Post;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RenderedContent;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_public_fonts_are_self_hosted_and_critical_portrait_uses_bundled_responsive_webp(): void
    {
        Storage::fake('local');
        $response = $this->get('/')->assertOk()->assertDontSee('fonts.googleapis.com', false)->assertDontSee('fonts.gstatic.com', false);
        $xpath = $this->xpath($response->getContent());
        $preload = $xpath->query('//link[@rel="preload" and @as="font"]')->item(0);
        $this->assertStringContainsString('/assets/fonts/manrope-latin-', $preload->getAttribute('href'));
        $source = $xpath->query('//picture/source[@type="image/webp"]')->item(0);
        $this->assertNotNull($source);
        $this->assertStringContainsString('640w', $source->getAttribute('srcset'));
        $image = $xpath->query('//img[contains(@class,"portrait")]')->item(0);
        $this->assertSame('eager', $image->getAttribute('loading'));
        $this->assertSame('high', $image->getAttribute('fetchpriority'));
        $this->assertSame('1896', $image->getAttribute('width'));
        $this->assertCount(2, glob(public_path('assets/fonts/*.woff2')));
    }

    public function test_rendered_body_cache_updates_when_content_or_image_metadata_changes(): void
    {
        Storage::fake('local');
        config(['performance.cache_rendered_content' => true]);
        $post = Post::factory()->create(['body' => '## Original content']);
        $this->assertStringContainsString('Original content', RenderedContent::body($post));
        $post->body = '## Revised content';
        $this->assertStringContainsString('Revised content', RenderedContent::body($post));
        $post->body = '![](/storage/posts/screen.png)';
        $post->body_image_metadata = [['src' => '/storage/posts/screen.png', 'alt' => 'First description']];
        $this->assertStringContainsString('First description', RenderedContent::body($post));
        $post->body_image_metadata = [['src' => '/storage/posts/screen.png', 'alt' => 'Updated description']];
        $this->assertStringContainsString('Updated description', RenderedContent::body($post));
    }

    public function test_preview_and_disabled_fragment_cache_render_without_persisting_the_body(): void
    {
        $post = Post::factory()->create(['body' => 'Private saved changes']);
        Cache::spy()->shouldNotReceive('remember');
        $this->assertStringContainsString('Private saved changes', RenderedContent::body($post, true));
        config(['performance.cache_rendered_content' => false]);
        $this->assertStringContainsString('Private saved changes', RenderedContent::body($post));
    }

    public function test_contact_html_stays_private_and_never_reuses_another_session_csrf_token(): void
    {
        $first = $this->withSession(['_token' => 'first-visitor-csrf-token'])->get('/contact')->assertOk()
            ->assertSee('value="first-visitor-csrf-token"', false);
        $this->assertStringContainsString('private', $first->headers->get('Cache-Control'));
        $this->withSession(['_token' => 'second-visitor-csrf-token'])->get('/contact')->assertOk()
            ->assertSee('value="second-visitor-csrf-token"', false)->assertDontSee('first-visitor-csrf-token');
    }

    public function test_a_failed_cache_store_reports_the_failure_and_still_renders_the_article(): void
    {
        Storage::fake('local');
        Exceptions::fake();
        $post = Post::factory()->create(['body' => '## Content remains available']);
        Cache::shouldReceive('remember')->once()->andThrow(new RuntimeException('Cache unavailable'));

        $this->assertStringContainsString('Content remains available', RenderedContent::body($post));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_deferred_widget_is_inert_until_the_loader_runs_and_is_absent_from_previews(): void
    {
        $code = '<script id="test-chat-widget">window.testChat = true;</script>';
        SiteSetting::factory()->create(['chat_widget_enabled' => true, 'chat_widget_load_mode' => 'interaction', 'chat_widget_code' => $code]);
        $response = $this->get('/')->assertOk()->assertSee('Open chat')->assertDontSee($code, false);
        $xpath = $this->xpath($response->getContent());
        $this->assertCount(0, $xpath->query('//script[@id="test-chat-widget"]'));
        $config = json_decode($xpath->query('//script[@id="chat-widget-config"]')->item(0)->textContent, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($code, $config['code']);
        $this->assertSame('interaction', $config['mode']);
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('projects.preview', $project))->assertOk()->assertDontSee('chat-widget-config', false);
    }

    public function test_admin_can_select_deferred_loading_without_replacing_the_widget_and_invalid_modes_are_refused(): void
    {
        $site = SiteSetting::factory()->create(['chat_widget_code' => '<script>window.chat=true;</script>']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditSiteSetting::class, ['record' => $site->id])->fillForm(['chat_widget_load_mode' => 'idle'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('idle', $site->fresh()->chat_widget_load_mode);
        $this->assertSame('<script>window.chat=true;</script>', $site->fresh()->chat_widget_code);
        Livewire::test(EditSiteSetting::class, ['record' => $site->id])->fillForm(['chat_widget_load_mode' => 'invalid'])->call('save')->assertHasFormErrors(['chat_widget_load_mode']);
    }
}
