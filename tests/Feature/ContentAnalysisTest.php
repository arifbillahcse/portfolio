<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use App\Support\ContentAnalysis;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentAnalysisTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function analyze(array $input, bool $hasBody = true, array $metadata = []): array
    {
        return ContentAnalysis::analyze($input, array_replace([
            'title' => 'Laravel deployment', 'description' => 'A practical deployment guide.',
            'canonical' => 'https://arif.example/blog/deploy', 'self_url' => 'https://arif.example/blog/deploy',
        ], $metadata), $hasBody);
    }

    public function test_code_is_excluded_from_words_headings_links_and_focus_coverage(): void
    {
        $fence = str_repeat(chr(96), 3);
        $body = "## Overview\n\nHelpful deployment steps.\n\n".$fence."sh\n# Not a heading\nsudo apt update\n[example](/missing)\n".$fence;
        $result = $this->analyze(['body' => $body, 'seo_focus_keyword' => 'sudo apt']);
        $this->assertSame(4, $result['stats']['words']);
        $this->assertSame(1, $result['stats']['headings']);
        $this->assertSame(1, $result['stats']['code_blocks']);
        $this->assertSame([], $result['links']);
        $this->assertNotContains('extra_h1', array_column($result['checks'], 'code'));
        $this->assertContains('code_review', array_column($result['checks'], 'code'));
        $focus = collect($result['checks'])->firstWhere('code', 'focus_topic');
        $this->assertStringContainsString('none of the checked', $focus['message']);
    }

    public function test_extra_h1_skipped_levels_and_command_headings_receive_distinct_notes(): void
    {
        $result = $this->analyze(['body' => "# sudo apt update\n\n### Deployment\n\nUseful explanation."]);
        $codes = array_column($result['checks'], 'code');
        $this->assertContains('extra_h1', $codes);
        $this->assertContains('heading_order', $codes);
        $this->assertContains('command_heading', $codes);
        $this->assertSame([1, 3], array_column($result['outline'], 'level'));
    }

    public static function focusPhrases(): array
    {
        return [
            'case independent' => ['Learn PHP today', 'php', true],
            'word boundaries' => ['PHPStorm workflow', 'PHP', false],
            'Bengali phrase' => ['আমার লিনাক্স সার্ভার পরিচালনা', 'লিনাক্স সার্ভার', true],
            'phrase spacing' => ["Linux\nserver setup", 'Linux server', true],
        ];
    }

    #[DataProvider('focusPhrases')]
    public function test_focus_matching_uses_unicode_phrase_boundaries(string $text, string $phrase, bool $expected): void
    {
        $this->assertSame($expected, ContentAnalysis::contains($text, $phrase));
    }

    public function test_inline_code_is_excluded_and_bengali_words_are_counted(): void
    {
        $result = $this->analyze(['body' => "লিনাক্স সার্ভার পরিচালনা\n\nUse ".chr(96).'sudo apt'.chr(96).' carefully.']);
        $this->assertSame(5, $result['stats']['words']);
        $adjacent = $this->analyze(['body' => 'Linux'.chr(96).'command'.chr(96).'server']);
        $this->assertSame(2, $adjacent['stats']['words']);
    }

    public function test_empty_metadata_empty_prose_and_summary_only_content_are_reported_without_false_static_page_warnings(): void
    {
        $empty = $this->analyze(['body' => ''], metadata: ['title' => '', 'description' => '']);
        $this->assertContains('missing_title', array_column($empty['checks'], 'code'));
        $this->assertContains('missing_description', array_column($empty['checks'], 'code'));
        $this->assertContains('body_empty', array_column($empty['checks'], 'code'));
        $same = $this->analyze(['body' => 'Managed Linux servers.', 'summary' => 'Managed Linux servers.']);
        $this->assertContains('summary_only', array_column($same['checks'], 'code'));
        $metadataOnly = $this->analyze([], false);
        $this->assertNotContains('body_empty', array_column($metadataOnly['checks'], 'code'));
        $this->assertSame('metadata', $metadataOnly['scope']);
    }

    public function test_image_review_honors_existing_alt_and_explicit_decoration(): void
    {
        $result = $this->analyze(['body' => "![](/storage/posts/missing.png)\n\n![Useful screenshot](/storage/posts/clear.png)\n\n![](/storage/posts/divider.png)",
            'body_image_metadata' => [['src' => '/storage/posts/divider.png', 'decorative' => true]]]);
        $this->assertSame(3, $result['stats']['images']);
        $this->assertSame(1, $result['stats']['missing_alt']);
        $this->assertContains('image_alt', array_column($result['checks'], 'code'));
    }

    public static function linkDestinations(): array
    {
        return [
            'relative sibling' => ['next', 'internal', '/blog/next'],
            'relative parent' => ['../services/linux#details', 'internal', '/services/linux'],
            'tracking' => ['/about/?utm_source=test', 'internal', '/about'],
            'wordpress query' => ['/?p=965&utm_source=test', 'internal', '/?p=965'],
            'foreign host' => ['https://arif.example.evil/about', 'external', null],
            'protocol relative' => ['//external.example/page', 'external', null],
            'email' => ['mailto:hello@example.com', null, null],
            'fragment' => ['#section', null, null],
            'credentials' => ['https://user:secret@arif.example/about', null, null],
            'unsupported scheme' => ['ftp://arif.example/about', null, null],
            'invalid wordpress ID' => ['/?p[]=965', null, null],
        ];
    }

    #[DataProvider('linkDestinations')]
    public function test_link_classification_resolves_paths_without_outbound_requests(string $href, ?string $kind, ?string $path): void
    {
        config(['app.url' => 'https://arif.example']);
        Http::preventStrayRequests();
        $result = ContentAnalysis::link($href, 'https://arif.example/blog/current');
        $this->assertSame($kind, $result['kind'] ?? null);
        $this->assertSame($path, $result['path'] ?? null);
        Http::assertNothingSent();
    }

    public function test_link_counts_and_generic_anchor_reviews_do_not_claim_availability(): void
    {
        config(['app.url' => 'https://arif.example']);
        $result = $this->analyze(['body' => '[Click here](/projects/example) and [Laravel manual](https://laravel.com/docs)']);
        $this->assertSame(1, $result['stats']['internal_links']);
        $this->assertSame(1, $result['stats']['external_links']);
        $this->assertSame('/projects/example', $result['links'][0]['path']);
        $this->assertContains('anchor_text', array_column($result['checks'], 'code'));
    }

    public function test_large_content_analysis_has_an_explicit_coverage_limit_and_no_score(): void
    {
        $result = $this->analyze(['body' => str_repeat('Long text. ', 10001)]);
        $this->assertContains('analysis_limit', array_column($result['checks'], 'code'));
        $this->assertNull($result['fingerprint']);
        $this->assertSame('info', collect($result['checks'])->firstWhere('code', 'long_paragraphs')['status']);
        $this->assertArrayNotHasKey('score', $result);
    }

    public function test_editor_feedback_uses_unsaved_changes_and_escapes_text_without_publishing(): void
    {
        $post = Post::factory()->create(['body' => 'Existing prose.']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(EditPost::class, ['record' => $post->id])
            ->fillForm(['body' => '# Extra heading', 'seo_focus_keyword' => 'topic </script><script>alert("seo")</script>'])
            ->assertSee('Content SEO review')->assertSee('H1 heading(s) appear inside the body')
            ->assertDontSee('<script>alert("seo")</script>', false);
        $this->assertSame('Existing prose.', $post->fresh()->body);
        $this->assertFalse($post->fresh()->is_published);
        Livewire::test(SeoSettings::class)->assertSee('Main-page metadata review only')->assertDontSee('No explanatory prose was found.');
    }

    public function test_visibility_and_related_topics_are_informational(): void
    {
        $result = $this->analyze(['body' => 'Linux server setup.', 'seo_focus_keyword' => 'Linux server', 'seo_related_keywords' => ['Linux', 'AWS'],
            'seo_robots' => 'noindex_follow', 'seo_canonical_url' => 'https://example.com/original']);
        $this->assertContains('noindex', array_column($result['checks'], 'code'));
        $this->assertContains('canonical_override', array_column($result['checks'], 'code'));
        $related = collect($result['checks'])->firstWhere('code', 'related_topics');
        $this->assertStringContainsString('1 of 2', $related['message']);
        $this->assertSame('info', $related['status']);
    }
}
