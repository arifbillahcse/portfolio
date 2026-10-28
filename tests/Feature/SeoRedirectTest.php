<?php

namespace Tests\Feature;

use App\Filament\Resources\SeoRedirects\Pages\CreateSeoRedirect;
use App\Filament\Resources\SeoRedirects\Pages\EditSeoRedirect;
use App\Models\Post;
use App\Models\SeoRedirect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoRedirectTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_only_enabled_rules_redirect_and_chains_resolve_in_one_hop(): void
    {
        $first = SeoRedirect::factory()->create(['source_path' => '/old-first', 'destination_path' => '/about']);
        $this->get('/old-first')->assertNotFound();
        $first->update(['enabled' => true]);
        SeoRedirect::factory()->create(['source_path' => '/older', 'destination_path' => '/old-first', 'status_code' => 302, 'enabled' => true]);
        $this->get('/old-first/')->assertStatus(301)->assertRedirect(url('/about'));
        $this->get('/older')->assertStatus(302)->assertRedirect(url('/about'));
        $this->getJson('/older')->assertNotFound();
    }

    public static function unsafeRules(): array
    {
        return [
            ['source_path', '/'], ['source_path', '//attacker.example/x'], ['source_path', '/admin/login'],
            ['source_path', '/storage/file.jpg'], ['source_path', '/wp-admin/'], ['source_path', '/%77p-admin/'],
            ['source_path', '/x/../about'], ['source_path', '/%252e%252e/about'], ['source_path', '/sitemap.xml'],
            ['source_path', '/old?token=secret'], ['destination_path', 'https://attacker.example/'],
            ['destination_path', '//attacker.example/'], ['destination_path', 'javascript:alert(1)'],
            ['destination_path', '/admin/projects'], ['destination_path', '/missing-target'],
            ['destination_path', '/legacy-test'], ['status_code', 307],
        ];
    }

    #[DataProvider('unsafeRules')]
    public function test_unsafe_rules_are_rejected_without_creating_a_redirect(string $field, mixed $value): void
    {
        $attributes = ['source_path' => '/legacy-test', 'destination_path' => '/about', 'status_code' => 301, 'enabled' => true];
        $attributes[$field] = $value;
        try {
            SeoRedirect::query()->create($attributes);
            $this->fail('Expected invalid redirect to be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('seo_redirects', 0);
    }

    public function test_loops_and_draft_targets_are_refused_and_deleted_targets_stop_redirecting(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'final-target']);
        $redirect = SeoRedirect::factory()->create(['source_path' => '/legacy', 'destination_path' => '/blog/final-target', 'enabled' => true]);
        try {
            SeoRedirect::factory()->create(['source_path' => '/blog/final-target', 'destination_path' => '/legacy', 'enabled' => true]);
            $this->fail('Expected a loop rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination_path', $exception->errors());
        }
        $post->update(['is_published' => false]);
        $this->get('/legacy')->assertNotFound();
        $redirect->update(['enabled' => false]);
        $this->assertFalse($redirect->fresh()->enabled);
        $this->assertDatabaseCount('seo_redirects', 1);
    }

    public function test_published_slug_changes_create_disabled_suggestions_without_overwriting_existing_rules(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'before']);
        $post->update(['slug' => 'after']);
        $suggestion = SeoRedirect::query()->where('source_path', '/blog/before')->sole();
        $this->assertFalse($suggestion->enabled);
        $this->assertSame('/blog/after', $suggestion->destination_path);
        $this->get('/blog/before')->assertNotFound();
        $suggestion->update(['enabled' => true]);
        $this->get('/blog/before')->assertStatus(301)->assertRedirect(url('/blog/after'));
        $draft = Post::factory()->create(['slug' => 'draft-before']);
        $draft->update(['slug' => 'draft-after']);
        $this->assertDatabaseCount('seo_redirects', 1);
    }

    public function test_404_monitor_stores_only_public_paths_and_sampled_counts_without_queries_or_identifiers(): void
    {
        config(['seo.log_not_found' => true]);
        $this->get('/missing-client-page?email=private@example.com')->assertNotFound();
        $this->get('/missing-client-page?token=secret')->assertNotFound();
        $this->getJson('/missing-json')->assertNotFound();
        $this->get('/assets/missing.png')->assertNotFound();
        $this->get('/wp-admin/missing')->assertNotFound();
        $this->assertDatabaseCount('seo_not_found', 1);
        $row = DB::table('seo_not_found')->sole();
        $this->assertSame('/missing-client-page', $row->path);
        $this->assertSame(2, $row->hits);
        $this->assertSame(['id', 'path_hash', 'path', 'hits', 'first_seen_at', 'last_seen_at'], array_keys((array) $row));
        for ($i = 0; $i < 35; $i++) {
            $this->get('/missing-client-page')->assertNotFound();
        }
        $this->assertSame(30, DB::table('seo_not_found')->sole()->hits);
    }

    public function test_expired_404_logs_are_pruned_without_removing_recent_paths(): void
    {
        $this->freezeTime();
        DB::table('seo_not_found')->insert([
            ['path_hash' => hash('sha256', '/expired'), 'path' => '/expired', 'hits' => 1, 'first_seen_at' => now()->subDays(40), 'last_seen_at' => now()->subDays(31)],
            ['path_hash' => hash('sha256', '/recent'), 'path' => '/recent', 'hits' => 2, 'first_seen_at' => now()->subDays(2), 'last_seen_at' => now()],
        ]);
        $this->artisan('portfolio:prune-seo-404')->expectsOutput('Pruned 1 expired 404 log entries.')->assertSuccessful();
        $this->assertDatabaseCount('seo_not_found', 1);
        $this->assertDatabaseHas('seo_not_found', ['path' => '/recent']);
    }

    public function test_admins_can_manage_rules_and_view_logs_while_other_users_are_forbidden(): void
    {
        $this->get('/admin/seo-redirects')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/seo-not-found')->assertForbidden();
        $this->get('/admin/seo-redirects')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateSeoRedirect::class)->fillForm(['source_path' => '/old-contact', 'destination_path' => '/contact', 'status_code' => 301, 'enabled' => true])->call('create')->assertHasNoFormErrors();
        $rule = SeoRedirect::query()->sole();
        Livewire::test(EditSeoRedirect::class, ['record' => $rule->id])->fillForm(['enabled' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($rule->fresh()->enabled);
        $this->get('/admin/seo-not-found')->assertOk()->assertSeeText('404 monitor');
    }

    public function test_bulk_import_validates_before_writes_and_preserves_existing_edits(): void
    {
        $rows = [['source_path' => '/batch', 'destination_path' => '/about', 'status_code' => 301, 'enabled' => true]];
        $disk = Storage::fake('seo-input');
        $disk->put('rules.json', json_encode($rows));
        $this->artisan('portfolio:import-redirects', ['file' => $disk->path('rules.json'), '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('seo_redirects', 0);
        $this->artisan('portfolio:import-redirects', ['file' => $disk->path('rules.json')])->assertSuccessful();
        $rule = SeoRedirect::query()->sole();
        $rule->update(['destination_path' => '/contact']);
        $before = $rule->fresh()->getRawOriginal();
        $this->artisan('portfolio:import-redirects', ['file' => $disk->path('rules.json')])->assertSuccessful();
        $this->assertSame($before, $rule->fresh()->getRawOriginal());
        $disk->put('bad.json', json_encode([['source_path' => '/another', 'destination_path' => '/about', 'status_code' => 301, 'enabled' => false], ['source_path' => '/bad', 'destination_path' => '/missing', 'status_code' => 301, 'enabled' => true]]));
        $this->artisan('portfolio:import-redirects', ['file' => $disk->path('bad.json')])->assertFailed();
        $this->assertDatabaseCount('seo_redirects', 1);
    }

    public function test_origin_normalization_is_opt_in_and_preserves_path_and_query(): void
    {
        config(['app.url' => 'https://portfolio.company', 'seo.canonical_origin_redirects' => true]);
        Request::setTrustedHosts([]);
        $this->app->instance('env', 'production');
        $this->get('http://portfolio.company/about?utm_source=test')->assertStatus(301)->assertRedirect('https://portfolio.company/about?utm_source=test');
        $this->get('https://www.portfolio.company/about')->assertStatus(301)->assertRedirect('https://portfolio.company/about');
        $this->get('https://portfolio.company/about')->assertOk();
        $this->get('https://other.portfolio.company/about')->assertStatus(400);
    }

    public function test_invalid_wordpress_ids_return_404_instead_of_the_homepage_and_known_pages_redirect(): void
    {
        $this->get('/?p=1012')->assertNotFound();
        $this->get('/?p[]=1')->assertNotFound();
        $this->get('/?page_id[]=1')->assertNotFound();
        $this->get('/?page_id=3730')->assertStatus(301)->assertRedirect(route('about'));
        $this->get('/?page_id=3761')->assertStatus(301)->assertRedirect(route('services'));
        $this->get('/?utm_source=test')->assertOk();
    }

    public function test_log_capacity_is_bounded_and_existing_entries_can_still_be_counted(): void
    {
        config(['seo.log_not_found' => true]);
        $this->freezeTime();
        for ($start = 0; $start < 5000; $start += 500) {
            $rows = [];
            for ($index = $start; $index < $start + 500; $index++) {
                $path = '/logged-'.$index;
                $rows[] = ['path_hash' => hash('sha256', $path), 'path' => $path, 'hits' => 1,
                    'first_seen_at' => now(), 'last_seen_at' => now()];
            }
            DB::table('seo_not_found')->insert($rows);
        }
        $this->get('/beyond-cap')->assertNotFound();
        $this->get('/logged-0')->assertNotFound();
        $this->assertDatabaseCount('seo_not_found', 5000);
        $this->assertDatabaseMissing('seo_not_found', ['path' => '/beyond-cap']);
        $this->assertDatabaseHas('seo_not_found', ['path' => '/logged-0', 'hits' => 2]);
    }

    public function test_admin_validation_failures_and_untrusted_log_paths_are_safe(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateSeoRedirect::class)->fillForm([
            'source_path' => '/admin/login', 'destination_path' => '/about', 'status_code' => 301, 'enabled' => true,
        ])->call('create')->assertHasFormErrors(['source_path']);
        $this->assertDatabaseCount('seo_redirects', 0);
        $path = '/<script>alert("log")</script>';
        DB::table('seo_not_found')->insert(['path_hash' => hash('sha256', $path), 'path' => $path, 'hits' => 1,
            'first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->get('/admin/seo-not-found')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("log")</script>', false);
    }
}
