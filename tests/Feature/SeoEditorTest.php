<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoEditorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    public function test_only_admins_can_access_the_seo_settings_page(): void
    {
        $url = route('filament.admin.pages.seo-settings');
        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->signIn();

        $this->get($url)->assertOk()->assertSeeText('SEO settings')->assertSeeText('Search preview');
    }

    public function test_main_page_metadata_and_templates_save_without_overwriting_contact_credentials_or_legacy_seo(): void
    {
        $site = SiteSetting::factory()->create(['seo_title' => 'Existing home title', 'contact_email' => 'keep@example.com', 'smtp_password' => 'private-smtp-secret']);
        $passwordBefore = $site->getRawOriginal('smtp_password');
        $this->signIn();

        Livewire::test(SeoSettings::class)->fillForm([
            'page_seo.about.seo_title' => 'Meet Arif',
            'page_seo.about.seo_description' => 'My development and cloud career.',
            'page_seo.about.seo_focus_keyword' => 'Arif Billah profile',
            'page_seo.about.seo_related_keywords' => ['Laravel', 'Linux'],
            'page_seo.contact.seo_robots' => 'noindex_follow',
            'seo_title_templates.pages' => '{title} | {name}',
        ])->set('data.smtp_password', 'tampered')->set('data.contact_email', 'tampered@example.com')
            ->call('save')->assertHasNoFormErrors()->assertDontSee('private-smtp-secret');

        $site->refresh();
        $this->assertSame('Meet Arif', $site->page_seo['about']['seo_title']);
        $this->assertSame(['Laravel', 'Linux'], $site->page_seo['about']['seo_related_keywords']);
        $this->assertSame('noindex_follow', $site->page_seo['contact']['seo_robots']);
        $this->assertSame('keep@example.com', $site->contact_email);
        $this->assertSame($passwordBefore, $site->getRawOriginal('smtp_password'));
        $this->assertSame('Existing home title', $site->seo_title);
        $this->get('/about')->assertOk()->assertSee('Meet Arif')->assertSee('My development and cloud career.');
    }

    public function test_global_indexing_status_is_visible_and_cannot_be_overridden_by_form_state(): void
    {
        config(['portfolio.indexable' => false]);
        $this->signIn();

        Livewire::test(SeoSettings::class)->assertSee('Global indexing is disabled.')
            ->fillForm(['page_seo.home.seo_robots' => 'index_follow'])
            ->set('data.indexable', true)->call('save')->assertHasNoFormErrors();

        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
        $this->assertFalse(config('portfolio.indexable'));
    }

    public static function contentEditors(): array
    {
        return [[Post::class, EditPost::class], [Project::class, EditProject::class], [Service::class, EditService::class]];
    }

    #[DataProvider('contentEditors')]
    public function test_each_content_editor_saves_shared_seo_controls_without_publishing(string $model, string $page): void
    {
        $entry = $model::factory()->create(['seo_title' => 'Previous title', 'seo_description' => 'Previous description.']);
        $this->signIn();

        Livewire::test($page, ['record' => $entry->id])->fillForm([
            'seo_title' => 'Reviewed title', 'seo_description' => 'Reviewed description.',
            'seo_focus_keyword' => 'my project topic', 'seo_related_keywords' => ['related topic'],
            'seo_canonical_url' => 'https://example.com/original', 'seo_robots' => 'noindex_follow',
            'seo_social_title' => 'Social title', 'seo_social_description' => 'Social description.',
        ])->call('save')->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame('Reviewed title', $entry->seo_title);
        $this->assertSame('https://example.com/original', $entry->seo_canonical_url);
        $this->assertSame('Social title', $entry->seo_social_title);
        $this->assertSame(['related topic'], $entry->seo_related_keywords);
        $this->assertFalse($entry->is_published);
    }

    public static function invalidMetadata(): array
    {
        return [
            'javascript canonical' => ['seo_canonical_url', 'javascript:alert(1)'],
            'relative canonical' => ['seo_canonical_url', '/somewhere'],
            'credential canonical' => ['seo_canonical_url', 'https://user:pass@example.com/'],
            'fragment canonical' => ['seo_canonical_url', 'https://example.com/#part'],
            'newline canonical' => ['seo_canonical_url', "https://example.com/\r\nInjected"],
            'invalid robots' => ['seo_robots', 'allow-all'],
            'too many topics' => ['seo_related_keywords', array_fill(0, 16, 'topic')],
            'oversized topic' => ['seo_related_keywords', [str_repeat('x', 81)], 'seo_related_keywords.0'],
            'oversized social description' => ['seo_social_description', str_repeat('x', 321)],
            'title must be text' => ['seo_title', ['unexpected']],
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function test_invalid_seo_values_do_not_update_existing_metadata(string $field, mixed $value, ?string $errorField = null): void
    {
        $project = Project::factory()->create(['seo_title' => 'Keep this title']);
        $before = $project->fresh()->getRawOriginal();
        $this->signIn();

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([$field => $value])
            ->call('save')->assertHasFormErrors([$errorField ?? $field]);

        $this->assertSame($before, $project->fresh()->getRawOriginal());
    }

    public static function invalidTemplates(): array
    {
        return [['{brand}'], ['{title} {unknown}'], ['<script>{title}</script>'], ["{title}\n{brand}"]];
    }

    #[DataProvider('invalidTemplates')]
    public function test_invalid_title_templates_are_rejected_with_a_useful_message(string $template): void
    {
        $site = SiteSetting::factory()->create();
        $this->signIn();

        Livewire::test(SeoSettings::class)->fillForm(['seo_title_templates.pages' => $template])
            ->call('save')->assertHasFormErrors(['seo_title_templates.pages'])
            ->assertSee('Include {title}; only {title}, {brand} and {name} tokens are supported.');

        $this->assertNull($site->fresh()->seo_title_templates);
    }

    public function test_social_images_can_be_uploaded_and_are_used_in_public_share_tags(): void
    {
        Storage::fake('public');
        $project = Project::factory()->published()->create();
        $this->signIn();

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'seo_social_image' => UploadedFile::fake()->image('social.png'),
            'seo_social_image_alt' => 'A project screenshot',
        ])->call('save')->assertHasNoFormErrors();

        $path = $project->fresh()->seo_social_image;
        $this->assertStringStartsWith('profile/seo/', $path);
        Storage::disk('public')->assertExists($path);
        $this->get('/projects/'.$project->slug)->assertOk()->assertSee('/storage/'.$path, false)->assertSee('A project screenshot');
    }

    public function test_duplicate_metadata_and_escaped_previews_are_visible_to_the_editor(): void
    {
        Project::factory()->create(['seo_title' => 'Duplicate title', 'seo_description' => 'Duplicate description.']);
        $project = Project::factory()->create();
        $this->signIn();

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'seo_title' => 'Duplicate title', 'seo_description' => 'Duplicate description.',
        ])->assertSee('Another item of this content type uses the same SEO title.')
            ->assertSee('Another item of this content type uses the same SEO description.');
        Livewire::test(SeoSettings::class)->set('activePage', 'about')->fillForm([
            'page_seo.about.seo_title' => 'Same main page',
            'page_seo.contact.seo_title' => 'Same main page',
            'page_seo.home.seo_social_title' => '<script>alert("preview")</script>',
        ])->assertSee('Another main page uses the same SEO title: Contact.')
            ->set('activePage', 'home')->assertDontSee('<script>alert("preview")</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_overview_uses_effective_defaults_and_reports_missing_and_duplicate_metadata(): void
    {
        Profile::factory()->create(['name' => 'Arif Billah', 'home_intro' => null]);
        SiteSetting::factory()->create();
        $this->signIn();

        $editor = Livewire::test(SeoSettings::class)->assertSee('Page overview');
        $pages = $editor->viewData('pages');
        $this->assertCount(6, $pages);
        $this->assertSame('About | Arif Billah', $pages['about']['title']);
        $this->assertFalse($pages['about']['custom_title']);
        $this->assertFalse($pages['about']['custom_description']);
        $this->assertSame([], $pages['about']['reviews']);
        $this->assertStringContainsString('description is empty', implode(' ', $pages['home']['reviews']));

        $editor->fillForm([
            'page_seo.about.seo_title' => 'Shared title',
            'page_seo.contact.seo_title' => 'Shared title',
            'page_seo.about.seo_description' => 'Shared description.',
            'page_seo.contact.seo_description' => 'Shared description.',
        ]);
        foreach (['about', 'contact'] as $key) {
            $reviews = implode(' ', $editor->viewData('pages')[$key]['reviews']);
            $this->assertStringContainsString('same SEO title', $reviews);
            $this->assertStringContainsString('same meta description', $reviews);
        }
    }

    public function test_page_shortcuts_preserve_unsaved_edits_and_save_metadata_for_each_page(): void
    {
        $site = SiteSetting::factory()->create(['smtp_password' => 'private-seo-overview-secret']);
        $this->signIn();

        $editor = Livewire::test(SeoSettings::class)->set('activePage', 'about')
            ->assertSee('Editing About SEO')
            ->fillForm(['page_seo.about.seo_title' => 'About the developer'])
            ->set('activePage', 'contact')->assertSee('Editing Contact SEO')
            ->fillForm(['page_seo.contact.seo_description' => 'Discuss a Laravel project.'])
            ->set('activePage', 'about')->assertSee('Custom SEO title')
            ->assertDontSee('private-seo-overview-secret');

        $this->assertSame('About the developer | Arif', $editor->viewData('pages')['about']['title']);
        $this->assertSame('Discuss a Laravel project.', $editor->viewData('pages')['contact']['description']);
        $this->assertNull($site->fresh()->page_seo);
        $editor->fillForm(['seo_title_templates.pages' => '{title} | {brand}']);
        $this->assertSame('About the developer | Arif Portfolio', $editor->viewData('pages')['about']['title']);
        $editor->call('save')->assertHasNoFormErrors();
        $this->assertSame('About the developer', $site->fresh()->page_seo['about']['seo_title']);
        $this->assertSame('Discuss a Laravel project.', $site->fresh()->page_seo['contact']['seo_description']);
        $this->get('/about')->assertOk()->assertSee('<title>About the developer | Arif Portfolio</title>', false);
    }

    public function test_overview_visibility_matches_global_listing_page_and_sitemap_restrictions(): void
    {
        config(['portfolio.indexable' => true]);
        Post::factory()->published()->create(['is_demo' => true]);
        Post::factory()->create();
        Project::factory()->published()->create(['seo_robots' => 'noindex_follow']);
        $this->signIn();

        $editor = Livewire::test(SeoSettings::class);
        $pages = $editor->viewData('pages');
        $this->assertSame('noindex, follow', $pages['blog']['robots']);
        $this->assertFalse($pages['blog']['sitemap']);
        $this->assertSame('index, follow', $pages['projects']['robots']);
        $this->assertFalse($pages['projects']['sitemap']);

        Post::factory()->published()->create();
        $editor->call('$refresh')->fillForm([
            'page_seo.about.seo_canonical_url' => 'https://example.com/original',
            'page_seo.contact.seo_robots' => 'noindex_follow',
        ]);
        $pages = $editor->viewData('pages');
        $this->assertSame('index, follow', $pages['blog']['robots']);
        $this->assertTrue($pages['blog']['sitemap']);
        $this->assertSame('index, follow', $pages['about']['robots']);
        $this->assertFalse($pages['about']['sitemap']);
        $this->assertTrue($pages['about']['canonical_elsewhere']);
        $this->assertSame('noindex, follow', $pages['contact']['robots']);
        $this->assertFalse($pages['contact']['sitemap']);

        config(['portfolio.indexable' => false]);
        $editor->call('$refresh');
        $this->assertSame(6, $editor->viewData('noindexCount'));
        foreach ($editor->viewData('pages') as $page) {
            $this->assertSame('noindex, nofollow', $page['robots']);
            $this->assertFalse($page['sitemap']);
        }
    }

    public function test_page_links_open_the_requested_editor_and_reject_unknown_pages(): void
    {
        $this->signIn();
        $url = route('filament.admin.pages.seo-settings');
        $this->get($url.'?page=contact')->assertOk()->assertSee('Editing Contact SEO');
        $this->get($url.'?page=unknown')->assertNotFound();
        Livewire::test(SeoSettings::class)->set('activePage', 'unknown')->assertNotFound();
    }

    public function test_overview_flags_overlong_metadata_and_escapes_editor_text(): void
    {
        $this->signIn();
        $title = '<script>alert("overview")</script>'.str_repeat(' long title', 8);
        $editor = Livewire::test(SeoSettings::class)->fillForm([
            'page_seo.about.seo_title' => $title,
            'page_seo.about.seo_description' => str_repeat('A useful sentence. ', 12),
        ])->assertDontSee('<script>alert("overview")</script>', false)->assertSee('&lt;script&gt;', false);

        $this->assertStringContainsString('Search text may be shortened', implode(' ', $editor->viewData('pages')['about']['reviews']));
    }
}
