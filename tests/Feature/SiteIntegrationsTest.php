<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteIntegrationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_integrations_are_absent_when_they_have_not_been_configured(): void
    {
        SiteSetting::factory()->create();
        $xpath = $this->document($this->get('/')->assertOk()->getContent());
        $this->assertCount(0, $xpath->query('//meta[@name="google-site-verification"]'));
        $this->assertCount(0, $xpath->query('//script[@id="test-chat-widget"]'));
    }

    #[DataProvider('verificationInputs')]
    public function test_admin_can_save_a_token_or_complete_google_meta_tag(string $input): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['google_site_verification' => $input])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('google-token_123-ABC', $settings->fresh()->google_site_verification);
    }

    public static function verificationInputs(): array
    {
        return [
            ['google-token_123-ABC'],
            ['  google-token_123-ABC  '],
            ['<meta name="google-site-verification" content="google-token_123-ABC" />'],
            ["<meta content='google-token_123-ABC' name='google-site-verification'>"],
        ];
    }

    public function test_google_meta_and_enabled_chat_embed_are_placed_correctly_on_all_public_pages(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();
        $code = '<script id="test-chat-widget">window.testChatWidget = true;</script>';
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm([
            'google_site_verification' => 'google-token_123-ABC', 'chat_widget_enabled' => true, 'chat_widget_code' => $code,
        ])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($settings->fresh()->chat_widget_enabled);
        $this->assertSame($code, $settings->fresh()->chat_widget_code);
        Post::factory()->published()->create(['slug' => 'public-entry']);
        Project::factory()->published()->create(['slug' => 'public-entry']);
        Service::factory()->published()->create(['slug' => 'public-entry']);
        foreach (['/', '/about', '/services', '/projects', '/blog', '/contact', '/blog/public-entry', '/projects/public-entry', '/services/public-entry'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $xpath = $this->document($html);
            $this->assertCount(1, $xpath->query('/html/head/meta[@name="google-site-verification" and @content="google-token_123-ABC"]'), $path);
            $this->assertCount(1, $xpath->query('/html/body/script[@id="test-chat-widget"]'), $path);
            $this->assertCount(0, $xpath->query('/html/head/script[@id="test-chat-widget"]'), $path);
            $this->assertGreaterThan(strpos($html, 'assets/js/script.js'), strpos($html, $code));
            $this->assertLessThan(strpos($html, '</body>'), strpos($html, $code));
        }
    }

    public function test_chat_widget_is_not_executed_in_admin_pages_or_login(): void
    {
        SiteSetting::factory()->create(['google_site_verification' => 'google-token', 'chat_widget_enabled' => true, 'chat_widget_code' => '<script id="test-chat-widget">window.testChatWidget = true;</script>']);
        $login = $this->document($this->get('/admin/login')->assertOk()->getContent());
        $this->assertCount(0, $login->query('//script[@id="test-chat-widget"]'));
        $this->assertCount(0, $login->query('//meta[@name="google-site-verification"]'));
        $this->admin();
        $settings = SiteSetting::query()->sole();
        foreach (['/admin', '/admin/profile', '/admin/site-settings/'.$settings->id.'/edit'] as $path) {
            $xpath = $this->document($this->get($path)->assertOk()->getContent());
            $this->assertCount(0, $xpath->query('//script[@id="test-chat-widget"]'), $path);
            $this->assertCount(0, $xpath->query('//meta[@name="google-site-verification"]'), $path);
        }
    }

    public function test_admin_can_disable_chat_without_losing_the_embed_and_clear_google_verification(): void
    {
        $this->admin();
        $code = '<script id="test-chat-widget">window.testChatWidget = true;</script>';
        $settings = SiteSetting::factory()->create(['google_site_verification' => 'google-token', 'chat_widget_enabled' => true, 'chat_widget_code' => $code]);
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['chat_widget_enabled' => false, 'google_site_verification' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertFalse($settings->fresh()->chat_widget_enabled);
        $this->assertSame($code, $settings->fresh()->chat_widget_code);
        $xpath = $this->document($this->get('/')->assertOk()->getContent());
        $this->assertCount(0, $xpath->query('//script[@id="test-chat-widget"]'));
        $this->assertCount(0, $xpath->query('//meta[@name="google-site-verification"]'));
    }

    #[DataProvider('invalidVerificationInputs')]
    public function test_invalid_google_values_are_rejected(string $input): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['google_site_verification' => $input])
            ->call('save')->assertHasFormErrors(['google_site_verification']);
        $this->assertNull($settings->fresh()->google_site_verification);
    }

    public static function invalidVerificationInputs(): array
    {
        return [
            ['"><script>alert(1)</script>'],
            ['<meta name="google-site-verification">'],
            ['<meta name="other-verification" content="test">'],
            [str_repeat('a', 513)],
        ];
    }

    public function test_google_verification_cannot_break_out_of_its_meta_attribute_even_if_database_value_is_invalid(): void
    {
        SiteSetting::factory()->create(['google_site_verification' => '"><script id="injected-google-script">alert(1)</script>']);
        $xpath = $this->document($this->get('/')->assertOk()->getContent());
        $this->assertCount(0, $xpath->query('//script[@id="injected-google-script"]'));
        $this->assertCount(1, $xpath->query('//head/meta[@name="google-site-verification"]'));
    }

    public function test_enabling_chat_requires_code_and_embed_size_is_limited(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();
        foreach (['', str_repeat('x', 30001)] as $code) {
            Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['chat_widget_enabled' => true, 'chat_widget_code' => $code])
                ->call('save')->assertHasFormErrors(['chat_widget_code']);
        }
        $this->assertFalse($settings->fresh()->chat_widget_enabled);
    }

    public function test_regular_users_cannot_change_integration_settings(): void
    {
        $settings = SiteSetting::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/site-settings/'.$settings->id.'/edit')->assertForbidden();
        Filament::setCurrentPanel('admin');
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->assertForbidden();
        $this->assertFalse($settings->fresh()->chat_widget_enabled);
        $this->assertNull($settings->fresh()->chat_widget_code);
    }
}
