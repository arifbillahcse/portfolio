<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function structuredData(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $node = (new DOMXPath($document))->query('//script[@type="application/ld+json"]')->item(0);

        return json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR);
    }

    private function xmlLocations(string $xml): array
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($xml));

        return array_map(fn ($node): string => $node->textContent, iterator_to_array($document->getElementsByTagName('loc')));
    }

    public function test_public_metadata_uses_the_configured_domain_and_strips_tracking_parameters(): void
    {
        config(['app.url' => 'https://arif.example', 'portfolio.indexable' => true]);
        SiteSetting::factory()->create(['brand_name' => 'Arif Studio']);
        Profile::factory()->create(['name' => 'Arif Billah']);
        $response = $this->get('/about?utm_source=test');
        $response->assertOk()->assertSee('rel="canonical" href="https://arif.example/about"', false)
            ->assertSee('property="og:url" content="https://arif.example/about"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('property="og:site_name" content="Arif Studio"', false);
        $schema = $this->structuredData($response->getContent());
        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('Person', $schema['@graph'][1]['@type']);
        $this->assertSame('Arif Billah', $schema['@graph'][1]['name']);
    }

    public function test_pagination_has_its_own_canonical_and_search_filters_are_noindex(): void
    {
        config(['app.url' => 'https://arif.example', 'portfolio.indexable' => true]);
        $this->get('/blog?page=2&utm_source=test')->assertOk()->assertSee('href="https://arif.example/blog?page=2"', false);
        $this->get('/blog?q=Laravel')->assertOk()->assertSee('content="noindex, follow"', false)
            ->assertSee('href="https://arif.example/blog?q=Laravel"', false);
        $this->get('/projects?category=apps')->assertOk()->assertSee('content="noindex, follow"', false);
    }

    public function test_article_schema_contains_the_real_author_dates_and_breadcrumbs(): void
    {
        config(['app.url' => 'https://arif.example', 'portfolio.indexable' => true]);
        Profile::factory()->create(['name' => 'Arif Billah']);
        $post = Post::factory()->published()->create(['slug' => 'laravel-guide', 'title' => 'Laravel guide', 'published_at' => now()->subDay(), 'cover_image' => 'posts/guide.webp']);
        $response = $this->get('/blog/laravel-guide')->assertOk()->assertSee('property="og:type" content="article"', false)
            ->assertSee('content="https://arif.example/storage/posts/guide.webp"', false);
        $graph = $this->structuredData($response->getContent())['@graph'];
        $this->assertSame('BreadcrumbList', $graph[1]['@type']);
        $this->assertSame('BlogPosting', $graph[2]['@type']);
        $this->assertSame('Laravel guide', $graph[2]['headline']);
        $this->assertSame('Arif Billah', $graph[2]['author']['name']);
        $this->assertSame($post->published_at->toIso8601String(), $graph[2]['datePublished']);
        $this->assertSame('https://arif.example/blog/laravel-guide', $graph[2]['mainEntityOfPage']);
    }

    public function test_service_schema_uses_the_profile_as_provider_and_samples_omit_article_schema(): void
    {
        $service = Service::factory()->published()->create();
        $response = $this->get('/services/'.$service->slug)->assertOk();
        $graph = $this->structuredData($response->getContent())['@graph'];
        $this->assertSame('Service', $graph[2]['@type']);
        Post::factory()->published()->create(['slug' => 'demo', 'is_demo' => true]);
        $response = $this->get('/blog/demo')->assertOk()->assertSee('content="noindex, follow"', false);
        $this->assertNotContains('BlogPosting', array_column($this->structuredData($response->getContent())['@graph'], '@type'));
    }

    public function test_json_ld_and_social_metadata_cannot_be_broken_out_of_by_cms_text(): void
    {
        $name = '</script><script>alert("xss")</script>';
        Profile::factory()->create(['name' => $name]);
        SiteSetting::factory()->create(['seo_title' => $name]);
        $response = $this->get('/')->assertOk()->assertDontSee($name, false);
        $this->assertSame($name, $this->structuredData($response->getContent())['@graph'][1]['name']);
    }

    public function test_live_robots_advertises_the_sitemap_and_blocks_private_endpoints(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://arif.example']);
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin', false)->assertSee('Disallow: /inquiries', false)->assertSee('Sitemap: https://arif.example/sitemap.xml', false);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_staging_is_noindex_and_does_not_advertise_public_sitemap_urls(): void
    {
        config(['portfolio.indexable' => false]);
        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex, nofollow"', false);
        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->assertOk()->getContent());
        $this->assertSame([], $this->xmlLocations($this->get('/sitemap.xml')->assertOk()->getContent()));
        $this->assertSame([], $this->xmlLocations($this->get('/sitemaps/pages.xml')->assertOk()->getContent()));
        $this->get('/sitemaps/projects/1.xml')->assertNotFound();
    }

    public function test_sitemaps_contain_only_canonical_published_non_demo_content(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://arif.example']);
        $this->freezeTime();
        $project = Project::factory()->published()->create(['slug' => 'live-project']);
        Project::factory()->create(['slug' => 'draft-project']);
        Project::factory()->published()->create(['slug' => 'future-project', 'published_at' => now()->addDay()]);
        Project::factory()->published()->create(['slug' => 'demo-project', 'is_demo' => true]);
        Post::factory()->published()->create(['slug' => 'real-article']);
        Post::factory()->published()->create(['slug' => 'sample-article', 'is_demo' => true]);
        Service::factory()->published()->create(['slug' => 'deployment']);
        Service::factory()->create(['slug' => 'draft-service']);

        $index = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertSame([
            'https://arif.example/sitemaps/pages.xml', 'https://arif.example/sitemaps/projects/1.xml',
            'https://arif.example/sitemaps/blog/1.xml', 'https://arif.example/sitemaps/services/1.xml',
        ], $this->xmlLocations($index->getContent()));
        $projects = $this->get('/sitemaps/projects/1.xml')->assertOk()->assertSee($project->updated_at->toIso8601String(), false);
        $this->assertSame(['https://arif.example/projects/live-project'], $this->xmlLocations($projects->getContent()));
        $this->assertSame(['https://arif.example/blog/real-article'], $this->xmlLocations($this->get('/sitemaps/blog/1.xml')->getContent()));
        $this->assertSame(['https://arif.example/services/deployment'], $this->xmlLocations($this->get('/sitemaps/services/1.xml')->getContent()));
        $pages = $this->xmlLocations($this->get('/sitemaps/pages.xml')->getContent());
        $this->assertContains('https://arif.example/blog', $pages);
        $this->assertContains('https://arif.example/contact', $pages);
        $this->assertCount(6, $pages);
    }

    public function test_empty_or_demo_only_collections_are_excluded_from_static_sitemap(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://arif.example']);
        Post::factory()->published()->create(['is_demo' => true]);
        Project::factory()->published()->create(['is_demo' => true]);
        $locations = $this->xmlLocations($this->get('/sitemaps/pages.xml')->getContent());
        $this->assertNotContains('https://arif.example/blog', $locations);
        $this->assertNotContains('https://arif.example/projects', $locations);
    }

    public function test_sitemap_shards_are_bounded_and_invalid_pages_return_not_found(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://arif.example']);
        $category = Category::factory()->create(['type' => 'project']);
        Project::factory()->published()->count(1001)->create(['category_id' => $category->id]);
        $this->get('/sitemap.xml')->assertSee('/sitemaps/projects/2.xml', false);
        $this->assertCount(1000, $this->xmlLocations($this->get('/sitemaps/projects/1.xml')->getContent()));
        $this->assertCount(1, $this->xmlLocations($this->get('/sitemaps/projects/2.xml')->getContent()));
        foreach (['/sitemaps/projects/3.xml', '/sitemaps/projects/0.xml', '/sitemaps/projects/999999999999999999999.xml', '/sitemaps/private/1.xml'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
