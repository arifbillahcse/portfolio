<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectWebsiteIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_home_listing_and_details_offer_safe_website_actions_with_separate_project_links(): void
    {
        $project = Project::factory()->published()->create([
            'title' => 'Store <preview>', 'external_url' => 'https://example.com/store?item=1&view=all',
            'cover_image' => 'projects/store.webp', 'gallery' => ['projects/store-home.webp', 'projects/store-shop.webp'],
        ]);

        foreach (['/', '/projects', route('projects.show', $project->slug)] as $url) {
            $xpath = $this->document($this->get($url)->assertOk()->getContent());
            $links = $xpath->query('//a[@href="https://example.com/store?item=1&view=all"]');
            $this->assertCount(1, $links);
            $this->assertSame('_blank', $links->item(0)->getAttribute('target'));
            $this->assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
            $this->assertCount(0, $xpath->query('//preview'));
        }
        $this->get('/projects')->assertOk()->assertSee(route('projects.show', $project->slug), false)
            ->assertSee('/storage/projects/store.webp', false);
        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSee('/storage/projects/store-home.webp', false)->assertSee('/storage/projects/store-shop.webp', false);
    }

    /** @return array<string, array{?string}> */
    public static function invalidWebsiteLinks(): array
    {
        return [
            'no website' => [null],
            'javascript' => ['javascript:alert(1)'],
            'FTP' => ['ftp://example.com/'],
            'protocol relative' => ['//example.com/'],
            'HTML' => ['<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('invalidWebsiteLinks')]
    public function test_invalid_website_values_do_not_render_visit_actions(?string $url): void
    {
        $project = Project::factory()->published()->create(['external_url' => $url]);

        $this->get('/projects')->assertOk()->assertDontSeeText('Visit website');
        $this->get(route('projects.show', $project->slug))->assertOk()->assertDontSeeText('Visit website');
    }

    public function test_website_drafts_are_private_but_admins_can_preview_their_content_and_links(): void
    {
        $this->artisan('portfolio:import-catalogue', ['file' => resource_path('catalogue/web-development.json')])->assertSuccessful();
        $project = Project::query()->where('catalogue_key', 'arif-web-development:web-002')->sole();

        $this->get('/')->assertOk()->assertDontSee('upcmartbd.com');
        $this->get('/projects')->assertOk()->assertDontSeeText('UPC Mart');
        $this->get(route('projects.show', $project->slug))->assertNotFound();
        $this->get('/sitemaps/projects/1.xml')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('projects.preview', $project))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('href="https://www.upcmartbd.com/"', false)->assertSeeText('UPC Mart')
            ->assertDontSee('Source skill claims')->assertDontSee('upcmart.com/logo.png')->assertDontSee('website_draft_candidate');
        $this->assertFalse($project->fresh()->is_published);
    }

    public function test_reviewed_websites_integrate_with_category_filters_pagination_and_public_seo_after_publication(): void
    {
        config(['portfolio.indexable' => true, 'app.url' => 'https://portfolio.company']);
        $this->artisan('portfolio:import-catalogue', ['file' => resource_path('catalogue/web-development.json')])->assertSuccessful();
        Project::query()->where('catalogue_key', 'like', 'arif-web-development:%')->update(['is_published' => true]);
        $project = Project::query()->where('catalogue_key', 'arif-web-development:web-002')->sole();

        $this->get('/projects')->assertOk()->assertSee('?page=2', false)
            ->assertViewHas('projects', fn ($projects): bool => $projects->total() === 22 && $projects->count() === 9);
        $this->get('/projects?page=3')->assertOk()->assertViewHas('projects', fn ($projects): bool => $projects->count() === 4);
        $this->get('/projects?category=commerce')->assertOk()->assertSeeText('UPC Mart')->assertDontSeeText('ProHost')
            ->assertViewHas('projects', fn ($projects): bool => $projects->total() === 6);
        $detail = $this->get(route('projects.show', $project->slug))->assertOk();
        $detail->assertSee('rel="canonical" href="https://portfolio.company/projects/upc-mart"', false)
            ->assertSee('content="Menswear storefront with a product-led shopping experience."', false)
            ->assertDontSee('Source skill claims')->assertDontSee('upcmart.com/logo.png')->assertDontSee('website_draft_candidate');
        $xpath = $this->document($detail->getContent());
        $schema = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame('CreativeWork', $schema['@graph'][2]['@type']);
        $this->assertSame('UPC Mart', $schema['@graph'][2]['name']);
        $this->get('/sitemaps/projects/1.xml')->assertOk()->assertSee('/projects/upc-mart', false)
            ->assertDontSee('/admin/projects/', false)->assertDontSee('sarionenasfoodhub.com')->assertDontSee('wp-admin');
    }
}
