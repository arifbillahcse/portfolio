<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoControlsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public static function contentTypes(): array
    {
        return ['article' => [Post::class, 'blog'], 'project' => [Project::class, 'projects'], 'service' => [Service::class, 'services']];
    }

    #[DataProvider('contentTypes')]
    public function test_published_content_uses_separate_search_and_social_metadata(string $model, string $section): void
    {
        config(['app.url' => 'https://portfolio.company', 'portfolio.indexable' => true]);
        SiteSetting::factory()->create(['brand_name' => 'Arif Billah']);
        $entry = $model::factory()->published()->create([
            'slug' => 'client-platform', 'seo_title' => 'Custom search title', 'seo_description' => 'Search summary.',
            'seo_social_title' => 'Share this project', 'seo_social_description' => 'A different social summary.',
            'seo_social_image' => 'profile/seo/share.webp', 'seo_social_image_alt' => 'Platform overview',
            'seo_focus_keyword' => 'private-editorial-topic', 'seo_related_keywords' => ['private-related-topic'],
        ]);

        $response = $this->get('/'.$section.'/'.$entry->slug)->assertOk();

        $response->assertSee('<title>Custom search title | Arif Billah</title>', false)
            ->assertSee('<meta name="description" content="Search summary.">', false)
            ->assertSee('property="og:title" content="Share this project"', false)
            ->assertSee('name="twitter:description" content="A different social summary."', false)
            ->assertSee('https://portfolio.company/storage/profile/seo/share.webp', false)
            ->assertSee('name="twitter:image:alt" content="Platform overview"', false)
            ->assertDontSee('private-editorial-topic')->assertDontSee('private-related-topic')->assertDontSee('name="keywords"', false);
    }

    public function test_main_pages_have_independent_overrides_and_home_keeps_legacy_fallbacks(): void
    {
        Profile::factory()->create(['name' => 'Arif Billah']);
        Post::factory()->published()->create();
        Project::factory()->published()->create();
        $pages = [];
        foreach (['home', 'about', 'services', 'projects', 'blog', 'contact'] as $page) {
            $pages[$page] = ['seo_title' => 'Custom '.$page, 'seo_description' => 'Description for '.$page];
        }
        $site = SiteSetting::factory()->create(['seo_title' => 'Existing home title', 'seo_description' => 'Existing home description.', 'page_seo' => $pages]);

        foreach (['home' => '/', 'about' => '/about', 'services' => '/services', 'projects' => '/projects', 'blog' => '/blog', 'contact' => '/contact'] as $page => $path) {
            $this->get($path)->assertOk()->assertSee('Custom '.$page)->assertSee('content="Description for '.$page.'"', false);
        }
        $site->update(['page_seo' => null]);
        $this->get('/')->assertOk()->assertSee('<title>Existing home title</title>', false)
            ->assertSee('content="Existing home description."', false);
        $this->assertSame('Existing home title', $site->fresh()->seo_title);
    }

    public function test_title_templates_apply_without_rewriting_existing_content_and_pagination_has_context(): void
    {
        $site = SiteSetting::factory()->create(['brand_name' => 'Arif Studio',
            'seo_title_templates' => ['blog' => '{title} — {name}', 'pages' => '{title} | {brand}']]);
        Profile::factory()->create(['name' => 'Arif Billah']);
        $post = Post::factory()->published()->create(['seo_title' => 'Existing edited title', 'slug' => 'existing-title']);
        Post::factory()->published()->count(9)->create();

        $this->get('/blog/existing-title')->assertOk()->assertSee('<title>Existing edited title — Arif Billah</title>', false);
        $this->get('/blog?page=2')->assertOk()->assertSee('<title>Blog — Page 2 | Arif Studio</title>', false)
            ->assertSee('/blog?page=2', false);
        $this->assertSame('Existing edited title', $post->fresh()->seo_title);
        $this->assertSame('Arif Studio', $site->fresh()->brand_name);
    }

    public static function robotOptions(): array
    {
        return [
            ['index_follow', 'index, follow'], ['index_nofollow', 'index, nofollow'],
            ['noindex_follow', 'noindex, follow'], ['noindex_nofollow', 'noindex, nofollow'],
        ];
    }

    #[DataProvider('robotOptions')]
    public function test_visibility_overrides_match_http_headers_and_sitemap_exclusions(string $setting, string $expected): void
    {
        config(['portfolio.indexable' => true]);
        $post = Post::factory()->published()->create(['slug' => 'visibility', 'seo_robots' => $setting]);

        $this->get('/blog/visibility')->assertOk()->assertHeader('X-Robots-Tag', $expected)
            ->assertSee('name="robots" content="'.$expected.'"', false);

        if (str_starts_with($expected, 'noindex')) {
            $this->get('/sitemaps/blog/1.xml')->assertNotFound();
        } else {
            $this->get('/sitemaps/blog/1.xml')->assertOk()->assertSee('/blog/visibility', false);
        }
        $this->assertTrue($post->fresh()->is_published);
    }

    public function test_canonical_overrides_do_not_redirect_visitors_and_only_self_canonicals_enter_the_sitemap(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://portfolio.company']);
        $external = Post::factory()->published()->create(['slug' => 'republication', 'seo_canonical_url' => 'https://original.example/article']);
        Post::factory()->published()->create(['slug' => 'self', 'seo_canonical_url' => 'https://portfolio.company/blog/self']);
        Post::factory()->published()->create(['slug' => 'automatic']);
        SiteSetting::factory()->create(['page_seo' => ['about' => ['seo_robots' => 'noindex_follow'], 'contact' => ['seo_canonical_url' => 'https://original.example/contact']]]);

        $this->get('/blog/republication')->assertOk()->assertSee('rel="canonical" href="https://original.example/article"', false);
        $this->get('/sitemaps/blog/1.xml')->assertOk()->assertSee('/blog/self', false)->assertSee('/blog/automatic', false)
            ->assertDontSee('/blog/republication', false);
        $this->get('/sitemaps/pages.xml')->assertOk()->assertDontSee('/about', false)->assertDontSee('/contact', false);
        $this->assertSame('republication', $external->fresh()->slug);
    }

    public function test_collection_overrides_cannot_collapse_pagination_or_enable_search_indexing(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://portfolio.company']);
        SiteSetting::factory()->create(['page_seo' => ['blog' => ['seo_canonical_url' => 'https://original.example/blog', 'seo_robots' => 'index_follow']]]);
        Post::factory()->published()->count(10)->create();

        $this->get('/blog?page=2&utm_source=test')->assertOk()
            ->assertSee('rel="canonical" href="https://portfolio.company/blog?page=2"', false);
        $this->get('/blog?q=test')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_global_controls_samples_and_private_previews_override_explicit_index_requests(): void
    {
        config(['portfolio.indexable' => false]);
        $project = Project::factory()->published()->create(['slug' => 'sample-project', 'seo_robots' => 'index_follow']);
        $this->get('/projects/sample-project')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        config(['portfolio.indexable' => true]);
        $project->update(['is_demo' => true]);
        $this->get('/projects/sample-project')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $project->update(['is_demo' => false, 'is_published' => false, 'seo_canonical_url' => 'https://external.example/article']);
        $this->get('/projects/sample-project')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('projects.preview', $project))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('https://external.example/article');
        $this->assertFalse($project->fresh()->is_published);
    }

    public function test_unsafe_stored_values_cannot_create_tags_links_or_external_image_requests(): void
    {
        SiteSetting::factory()->create(['seo_title_templates' => ['blog' => '<script>{title}</script>']]);
        $post = Post::factory()->published()->create(['slug' => 'escaped',
            'seo_title' => '<script>alert("title")</script>', 'seo_social_title' => '<img src=x onerror=alert(1)>',
            'seo_social_description' => '"><script>alert("description")</script>',
            'seo_canonical_url' => 'javascript:alert(1)', 'seo_social_image' => '../private.jpg',
            'seo_social_image_alt' => '" onerror="alert(1)', 'seo_robots' => 'index"><script>alert(1)</script>',
        ]);

        $response = $this->get('/blog/escaped')->assertOk();

        $response->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("title")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('href="javascript:', false)
            ->assertDontSee('../private.jpg', false);
        $xpath = $this->document($response->getContent());
        $this->assertCount(1, $xpath->query('//title'));
        $this->assertCount(1, $xpath->query('//link[@rel="canonical"]'));
        $this->assertCount(0, $xpath->query('//meta[@onerror]'));
        $this->assertSame('<script>alert("title")</script>', $post->fresh()->seo_title);
    }

    public function test_person_schema_keeps_the_portrait_when_a_social_share_image_is_selected(): void
    {
        Profile::factory()->create(['portrait_path' => 'profile/portrait.webp']);
        SiteSetting::factory()->create(['page_seo' => ['about' => ['seo_social_image' => 'profile/seo/social.webp']]]);

        $response = $this->get('/about')->assertOk();

        $xpath = $this->document($response->getContent());
        $schema = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, 64, JSON_THROW_ON_ERROR);
        $this->assertStringEndsWith('/storage/profile/portrait.webp', $schema['@graph'][1]['image']['contentUrl']);
        $this->assertStringEndsWith('/storage/profile/seo/social.webp', $xpath->query('//meta[@property="og:image"]')->item(0)->getAttribute('content'));
    }
}
