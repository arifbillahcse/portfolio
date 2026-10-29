<?php

namespace Tests\Feature;

use App\Filament\Pages\GrowthDashboard;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\GrowthReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function serviceAccount(): string
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $windowsConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($windowsConfig)) {
            $options['config'] = $windowsConfig;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);

        return json_encode(['client_email' => 'reports@example.iam.gserviceaccount.com', 'private_key' => $pem]);
    }

    private function connectedSite(): SiteSetting
    {
        return SiteSetting::factory()->create([
            'ga4_property_id' => '123456', 'search_console_property' => 'sc-domain:example.com',
            'google_service_account' => $this->serviceAccount(), 'next_content_review_on' => now()->subDay(), 'content_plan' => 'Publish a case study',
        ]);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    public function test_report_combines_google_data_with_attributed_leads(): void
    {
        $this->connectedSite();
        Inquiry::factory()->create(['utm_source' => 'newsletter']);
        Inquiry::factory()->count(2)->create(['referrer_host' => 'www.google.com']);
        Inquiry::factory()->create();
        Inquiry::factory()->create(['created_at' => now()->subDays(60), 'utm_source' => 'old']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-123']),
            'www.googleapis.com/webmasters/*' => Http::response(['rows' => [['keys' => ['laravel developer'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 8.24]]]),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '40'], ['value' => '30'], ['value' => '25']]]]]),
        ]);

        $report = app(GrowthReport::class)->build(28);

        $this->assertSame(4, $report['leads']['total']);
        $this->assertSame(3, $report['leads']['attributed']);
        $this->assertSame('www.google.com', $report['leads']['sources'][0]['name']);
        $this->assertSame(12, $report['search_console']['clicks']);
        $this->assertSame(3.5, $report['search_console']['ctr']);
        $this->assertSame(40, $report['analytics']['sessions']);
        $this->assertSame(10.0, $report['conversion_rate']);
        $this->assertTrue($report['plan']['overdue']);
        $this->assertSame([], $report['errors']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sites/sc-domain%3Aexample.com/searchAnalytics/query') && $request->hasHeader('Authorization', 'Bearer token-123'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'properties/123456:runReport'));
    }

    public function test_google_failures_are_reported_without_losing_local_lead_data(): void
    {
        $this->connectedSite();
        Inquiry::factory()->create();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-123']),
            'www.googleapis.com/*' => Http::response(['error' => ['message' => 'User does not have sufficient permission']], 403),
            'analyticsdata.googleapis.com/*' => Http::response([], 500),
        ]);

        $report = app(GrowthReport::class)->build();

        $this->assertSame(1, $report['leads']['total']);
        $this->assertNull($report['search_console']);
        $this->assertNull($report['analytics']);
        $this->assertStringContainsString('403', $report['errors']['search_console']);
        $this->assertArrayHasKey('analytics', $report['errors']);
    }

    public function test_without_credentials_google_is_never_called(): void
    {
        SiteSetting::factory()->create(['google_service_account' => '{"client_email":"a@b.c"}']);
        Http::fake();

        $report = app(GrowthReport::class)->build();

        Http::assertNothingSent();
        $this->assertFalse($report['connections']['search_console']);
        $this->assertFalse($report['connections']['analytics']);
    }

    public function test_command_saves_a_private_report_and_it_round_trips(): void
    {
        SiteSetting::factory()->create();
        Inquiry::factory()->create();

        $this->artisan('portfolio:sync-growth-report', ['--days' => 500])->expectsOutputToContain('1 leads in the last 90 days')->assertSuccessful();

        $report = app(GrowthReport::class)->read();
        $this->assertSame(1, $report['leads']['total']);
        $this->assertSame(90, $report['days']);
    }

    public function test_service_account_key_is_encrypted_and_hidden(): void
    {
        $site = $this->connectedSite();

        $this->assertStringNotContainsString('PRIVATE KEY', $site->getRawOriginal('google_service_account'));
        $this->assertArrayNotHasKey('google_service_account', $site->toArray());
    }

    public function test_admin_can_save_analytics_settings_and_invalid_values_are_rejected(): void
    {
        $this->admin();
        $site = SiteSetting::factory()->create();
        $account = $this->serviceAccount();

        Livewire::test(EditSiteSetting::class, ['record' => $site->id])
            ->fillForm(['analytics_enabled' => true, 'ga4_measurement_id' => 'bad id', 'google_service_account' => 'not json'])
            ->call('save')->assertHasFormErrors(['ga4_measurement_id', 'google_service_account']);

        Livewire::test(EditSiteSetting::class, ['record' => $site->id])
            ->fillForm(['analytics_enabled' => true, 'ga4_measurement_id' => 'G-ABCD1234', 'ga4_property_id' => '123456',
                'search_console_property' => 'sc-domain:example.com', 'google_service_account' => $account])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($account, $site->fresh()->google_service_account);

        Livewire::test(EditSiteSetting::class, ['record' => $site->id])->fillForm(['google_service_account' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($account, $site->fresh()->google_service_account);
    }

    public function test_only_admins_can_open_the_dashboard(): void
    {
        SiteSetting::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Filament::setCurrentPanel('admin');
        $this->assertFalse(GrowthDashboard::canAccess());

        $this->admin();
        Livewire::test(GrowthDashboard::class)->assertSee('No growth report yet')
            ->callAction('refresh')->assertSee('Leads by source');
    }
}
