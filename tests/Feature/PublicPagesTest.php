<?php

namespace Tests\Feature;

use Database\Seeders\PortfolioContentSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PortfolioContentSeeder::class);
    }

    /**
     * @return array<string, array{string, string, string, list<string>}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['/', 'home', 'Hi, I’m Arif.', ['hireModal', 'openHire', 'hireForm']],
            'about' => ['/about', 'about', 'A developer’s mindset.', ['tab-education', 'tab-skills', 'tab-experience']],
            'services' => ['/services', 'services', 'Build your platform', ['development', 'integrations', 'infrastructure', 'care']],
            'projects' => ['/projects', 'projects', 'Connected from code to cloud.', []],
            'blog' => ['/blog', 'blog', 'Explore the journal', ['articleSearch']],
            'contact' => ['/contact', 'contact', 'Our first conversation.', ['contactForm', 'formStatus', 'clientMessage']],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render_the_design_and_correct_navigation(string $path, string $page, string $heading, array $controls): void
    {
        $response = $this->get($path);

        $response->assertOk()
            ->assertViewIs('pages.'.$page)
            ->assertSeeText($heading)
            ->assertSee('data-page="'.$page.'"', false)
            ->assertSee('/assets/css/styles.css', false)
            ->assertSee('/assets/js/script.js', false)
            ->assertDontSee('.html"', false);

        foreach ($controls as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }

        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $activeLinks = $xpath->query('//header//a[@aria-current="page"]');

        $this->assertCount(1, $xpath->query('//header'));
        $this->assertCount(1, $xpath->query('//footer'));
        $this->assertCount(1, $xpath->query('//h1'));
        $this->assertCount(1, $activeLinks);
        $this->assertSame($path, parse_url($activeLinks->item(0)->getAttribute('href'), PHP_URL_PATH) ?: '/');
        $this->assertCount(6, $xpath->query('//header//div[@id="nav"]/a'));

        foreach (['theme', 'menu', 'nav'] as $id) {
            $this->assertCount(1, $xpath->query('//*[@id="'.$id.'"]'));
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function legacyPages(): array
    {
        return [
            'home' => ['/index.html', '/'],
            'about' => ['/about.html', '/about'],
            'services' => ['/services.html', '/services'],
            'projects' => ['/projects.html', '/projects'],
            'blog' => ['/blog.html', '/blog'],
            'contact' => ['/contact.html', '/contact'],
        ];
    }

    #[DataProvider('legacyPages')]
    public function test_old_html_links_permanently_redirect_to_the_new_page(string $legacy, string $destination): void
    {
        $this->get($legacy)->assertMovedPermanently()->assertRedirect($destination);
    }

    public function test_sample_blog_retains_its_indexing_restriction(): void
    {
        $this->get('/blog')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_home_portrait_uses_the_published_image_path(): void
    {
        $this->get('/')->assertOk()->assertSee('/assets/images/arif-formal.png', false);
    }

    public function test_unpublished_pages_and_original_workspace_files_are_not_served(): void
    {
        $this->get('/not-a-public-page')->assertNotFound();
        $this->get('/portfolio-single-page-backup.html')->assertNotFound();
        $this->get('/backend.ps1')->assertNotFound();
    }
}
