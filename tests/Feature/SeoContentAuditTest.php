<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentAudit;
use App\Models\Post;
use App\Models\Project;
use App\Models\SeoRedirect;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SeoContentAudit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SeoContentAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function row(array $report, string $key): array
    {
        return collect($report['entries'])->firstWhere('key', $key);
    }

    public function test_cross_type_duplicates_are_reported_without_changing_content_or_exposing_smtp_secrets(): void
    {
        Http::preventStrayRequests();
        SiteSetting::factory()->create(['smtp_password' => 'private-mail-password']);
        $post = Post::factory()->published()->create(['seo_title' => 'Shared topic', 'seo_description' => 'Shared useful description.', 'seo_focus_keyword' => 'Linux setup', 'body' => 'Identical explanatory prose.']);
        $service = Service::factory()->published()->create(['seo_title' => 'Shared topic', 'seo_description' => 'Shared useful description.', 'seo_focus_keyword' => 'linux SETUP', 'body' => 'Identical explanatory prose.']);
        $before = $post->fresh()->getRawOriginal();
        $report = app(SeoContentAudit::class)->run();
        $codes = array_column($this->row($report, 'blog:'.$post->id)['checks'], 'code');
        $this->assertContains('duplicate_search_title', $codes);
        $this->assertContains('duplicate_search_description', $codes);
        $this->assertContains('duplicate_topic', $codes);
        $this->assertContains('duplicate_fingerprint', $codes);
        $this->assertSame(8, $report['totals']['pages']);
        $this->assertSame('published', $this->row($report, 'services:'.$service->id)['state']);
        $this->assertSame($before, $post->fresh()->getRawOriginal());
        $this->assertStringNotContainsString('private-mail-password', json_encode($report));
        Http::assertNothingSent();
    }

    public function test_link_graph_ignores_private_sources_and_labels_unlinked_pages_as_candidates(): void
    {
        config(['app.url' => 'https://arif.example']);
        $target = Post::factory()->published()->create(['slug' => 'linux-monitoring', 'title' => 'Linux server monitoring']);
        $source = Project::factory()->published()->create(['title' => 'Linux server deployment', 'body' => '[Monitoring guide](/blog/linux-monitoring#details)']);
        Post::factory()->create(['body' => '[Deployment](/projects/'.$source->slug.')']);
        $report = app(SeoContentAudit::class)->run();
        $targetRow = $this->row($report, 'blog:'.$target->id);
        $sourceRow = $this->row($report, 'projects:'.$source->id);
        $this->assertSame(1, $targetRow['incoming_contextual']);
        $this->assertNotContains('contextual_orphan', array_column($targetRow['checks'], 'code'));
        $this->assertSame(0, $sourceRow['incoming_contextual']);
        $this->assertContains('contextual_orphan', array_column($sourceRow['checks'], 'code'));
        $this->assertSame('Linux server monitoring', $sourceRow['suggestions'][0]['title']);
    }

    public function test_missing_draft_redirected_and_restricted_link_targets_are_checked_without_http(): void
    {
        config(['app.url' => 'https://arif.example']);
        Http::preventStrayRequests();
        $target = Post::factory()->published()->create(['slug' => 'destination', 'wordpress_id' => 965, 'wordpress_path' => '/']);
        Post::factory()->create(['slug' => 'draft']);
        Service::factory()->published()->create(['slug' => 'restricted', 'seo_robots' => 'noindex_follow']);
        SeoRedirect::factory()->create(['source_path' => '/jump', 'destination_path' => '/blog/destination', 'enabled' => true]);
        $source = Post::factory()->published()->create(['body' => '[Missing](/missing) [Draft](/blog/draft) [ID](/?p=965) [About](/about.html) [Jump](/jump) [File](/assets/manual.pdf) [Noindex](/services/restricted) [Home](/)']);
        $report = app(SeoContentAudit::class)->run();
        $checks = collect($this->row($report, 'blog:'.$source->id)['checks'])->keyBy('code');
        $this->assertStringContainsString('/missing', $checks['unavailable_links']['message']);
        $this->assertStringContainsString('/blog/draft', $checks['unavailable_links']['message']);
        $this->assertStringNotContainsString('/assets/manual.pdf', $checks['unavailable_links']['message']);
        $this->assertStringContainsString('/?p=965', $checks['redirected_links']['message']);
        $this->assertStringContainsString('/jump', $checks['redirected_links']['message']);
        $this->assertStringContainsString('/services/restricted', $checks['restricted_links']['message']);
        $this->assertSame(1, $this->row($report, 'blog:'.$target->id)['incoming_contextual']);
        Http::assertNothingSent();
    }

    public function test_default_scope_excludes_private_and_sample_records_and_all_scope_labels_them(): void
    {
        $this->freezeTime();
        $draft = Post::factory()->create();
        $future = Post::factory()->published()->create(['published_at' => now()->addDay()]);
        $sample = Project::factory()->published()->create(['is_demo' => true]);
        $audit = app(SeoContentAudit::class);
        $this->assertSame(6, $audit->run()['totals']['pages']);
        $all = $audit->run(true);
        $this->assertSame(9, $all['totals']['pages']);
        $this->assertSame('draft', $this->row($all, 'blog:'.$draft->id)['state']);
        $this->assertSame('scheduled', $this->row($all, 'blog:'.$future->id)['state']);
        $this->assertSame('sample', $this->row($all, 'projects:'.$sample->id)['state']);
        $this->assertNotContains('contextual_orphan', array_column($this->row($all, 'blog:'.$draft->id)['checks'], 'code'));
    }

    public function test_command_saves_a_readable_private_snapshot_and_json_mode_prints_valid_json(): void
    {
        Storage::fake('local');
        config(['portfolio.indexable' => false]);
        $this->artisan('portfolio:seo-content-audit', ['--save' => true])->assertSuccessful();
        Storage::disk('local')->assertExists(SeoContentAudit::REPORT_PATH);
        $report = app(SeoContentAudit::class)->read();
        $this->assertSame(6, $report['totals']['pages']);
        $this->assertFalse($report['indexing_enabled']);
        $this->assertSame(0, Artisan::call('portfolio:seo-content-audit', ['--json' => true]));
        $json = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $json['version']);
        $this->assertSame('published', $json['scope']);
    }

    public function test_a_lock_prevents_concurrent_report_generation(): void
    {
        Storage::fake('local');
        $lock = Cache::lock('seo-content-audit', 3600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('portfolio:seo-content-audit', ['--save' => true])
                ->expectsOutput('A content audit is already running. Try again when it finishes.')->assertFailed();
            Storage::disk('local')->assertMissing(SeoContentAudit::REPORT_PATH);
        } finally {
            $lock->release();
        }
    }

    public function test_a_failed_snapshot_write_preserves_the_previous_report_and_returns_failure(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(SeoContentAudit::REPORT_PATH, '{"previous":"preserved"}');
        File::shouldReceive('ensureDirectoryExists')->once();
        File::shouldReceive('replace')->once()->andThrow(new RuntimeException('Storage unavailable'));
        $this->artisan('portfolio:seo-content-audit', ['--save' => true])->assertFailed();
        $this->assertSame('{"previous":"preserved"}', Storage::disk('local')->get(SeoContentAudit::REPORT_PATH));
    }

    public function test_only_admins_can_read_download_and_filter_the_report_and_content_is_escaped(): void
    {
        Storage::fake('local');
        $url = route('filament.admin.pages.seo-content-audit');
        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $post = Post::factory()->published()->create(['title' => '<script>alert("audit")</script>']);
        $audit = app(SeoContentAudit::class);
        $audit->save($audit->run());
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        $this->get($url)->assertOk()->assertSeeText('Content audit')->assertDontSee('<script>alert("audit")</script>', false);
        Livewire::test(ContentAudit::class)->set('search', 'alert')->assertSee('&lt;script&gt;', false)
            ->set('kind', 'blog')->assertSee('incoming contextual source pages');
        Livewire::test(ContentAudit::class)->call('download')->assertFileDownloaded('seo-content-audit.json');
        $this->assertTrue($post->fresh()->is_published);
    }

    public function test_missing_and_malformed_snapshots_show_recovery_instructions_and_filters_are_validated(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(ContentAudit::class)->assertSee('No readable saved report');
        Storage::disk('local')->put(SeoContentAudit::REPORT_PATH, '{"version":1,"entries":["malformed"]}');
        Livewire::test(ContentAudit::class)->assertSee('No readable saved report');
        Livewire::test(ContentAudit::class)->set('kind', 'not-a-section')->assertHasErrors(['kind']);
    }

    public function test_reading_public_pages_does_not_run_or_load_the_bulk_audit(): void
    {
        $this->mock(SeoContentAudit::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldNotReceive('run');
            $mock->shouldNotReceive('read');
        });
        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
    }

    public function test_saved_report_paginates_filters_and_handles_an_invalid_page_without_rescanning(): void
    {
        Storage::fake('local');
        $posts = Post::factory()->published()->count(21)->create();
        $audit = app(SeoContentAudit::class);
        $audit->save($audit->run());
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        $page = Livewire::test(ContentAudit::class)->set('kind', 'blog')->call('setPage', 2);
        $this->assertSame(1, $page->viewData('entries')->count());
        $this->assertSame($posts->last()->id, $page->viewData('entries')->first()['id']);
        $page->set('search', $posts->first()->slug)->assertSet('paginators.page', 1);
        $this->assertSame(1, $page->viewData('entries')->count());
        $page->set('search', '')->call('setPage', 'invalid');
        $this->assertSame(1, $page->viewData('entries')->currentPage());
        $this->assertSame(20, $page->viewData('entries')->count());
    }
}
