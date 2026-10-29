<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnalyticsTrackingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Client Example', 'email' => 'client@example.com', 'message' => 'I need a Laravel application and deployment.', 'source' => 'contact', 'website' => ''];
    }

    public function test_tracking_is_absent_until_enabled_with_a_valid_measurement_id(): void
    {
        SiteSetting::factory()->create(['ga4_measurement_id' => 'G-ABCD1234']);
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);

        SiteSetting::query()->update(['analytics_enabled' => true, 'ga4_measurement_id' => 'not-an-id']);
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_tracking_renders_for_public_pages_when_enabled(): void
    {
        SiteSetting::factory()->create(['analytics_enabled' => true, 'ga4_measurement_id' => 'G-ABCD1234']);

        $this->get('/')->assertOk()->assertSee('G-ABCD1234', false)->assertSee('googletagmanager.com/gtag/js', false)
            ->assertSee('doNotTrack', false)->assertDontSee("lead('form')", false);
    }

    public function test_tracking_is_disabled_when_indexing_is_off(): void
    {
        config(['portfolio.indexable' => false]);
        SiteSetting::factory()->create(['analytics_enabled' => true, 'ga4_measurement_id' => 'G-ABCD1234']);

        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_a_form_redirect_success_fires_the_lead_event(): void
    {
        SiteSetting::factory()->create(['analytics_enabled' => true, 'ga4_measurement_id' => 'G-ABCD1234']);

        $this->withSession(['inquiry_success' => 'Thanks'])->get('/contact')->assertSee("lead('form')", false);
    }

    public function test_an_inquiry_keeps_the_first_marketing_touch(): void
    {
        SiteSetting::factory()->create();

        $this->withHeader('referer', 'https://www.google.com/search?q=secret')
            ->get('/services?utm_source=Newsletter&utm_medium=email<script>&utm_campaign=launch')->assertOk();
        $this->get('/about?utm_source=other')->assertOk();
        $this->postJson(route('inquiries.store'), $this->payload())->assertCreated();

        $inquiry = Inquiry::query()->sole();
        $this->assertSame('/services', $inquiry->landing_path);
        $this->assertSame('www.google.com', $inquiry->referrer_host);
        $this->assertSame('Newsletter', $inquiry->utm_source);
        $this->assertSame('emailscript', $inquiry->utm_medium);
        $this->assertSame('launch', $inquiry->utm_campaign);
    }

    public function test_internal_navigation_and_untagged_visits_are_not_attributed(): void
    {
        SiteSetting::factory()->create();

        $this->withHeader('referer', rtrim(config('app.url'), '/').'/about')->get('/contact')->assertOk();
        $this->postJson(route('inquiries.store'), $this->payload())->assertCreated();

        $inquiry = Inquiry::query()->sole();
        $this->assertNull($inquiry->referrer_host);
        $this->assertNull($inquiry->utm_source);
        $this->assertNull($inquiry->landing_path);
    }
}
