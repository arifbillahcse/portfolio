<?php

namespace Tests\Feature;

use App\Jobs\SendInquiryNotification;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InquirySubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace(['name' => 'Client Example', 'email' => 'client@example.com', 'phone' => '+8801779440297',
            'message' => 'I need a Laravel application and deployment.', 'source' => 'contact', 'website' => ''], $overrides);
    }

    public function test_a_valid_inquiry_is_saved_and_email_notification_is_queued(): void
    {
        config(['mail.default' => 'smtp']);
        SiteSetting::factory()->create(['contact_email' => 'owner@example.com']);
        Queue::fake([SendInquiryNotification::class]);

        $this->postJson(route('inquiries.store'), $this->payload(['status' => 'replied', 'admin_notes' => 'Injected', 'notification_status' => 'sent']))
            ->assertCreated()->assertJsonStructure(['message'])->assertJsonMissingPath('email');
        $inquiry = Inquiry::query()->sole();
        $this->assertSame('Client Example', $inquiry->name);
        $this->assertSame('new', $inquiry->status);
        $this->assertNull($inquiry->admin_notes);
        $this->assertSame('pending', $inquiry->notification_status);
        Queue::assertPushed(SendInquiryNotification::class, fn ($job) => $job->inquiryId === $inquiry->id && $job->recipient === 'owner@example.com' && $job->queue === 'mail');
    }

    public function test_home_hire_submissions_work_without_javascript_and_redirect_with_a_receipt(): void
    {
        Queue::fake([SendInquiryNotification::class]);
        $this->post(route('inquiries.store'), $this->payload(['source' => 'home', 'phone' => null]))
            ->assertRedirect(route('contact'))->assertSessionHas('inquiry_success');
        $this->assertDatabaseHas('inquiries', ['source' => 'home', 'phone' => null]);
        Queue::assertNothingPushed();
        $this->get('/contact')->assertSeeText('Your inquiry has been received.');
    }

    public function test_unconfigured_mail_does_not_lose_inquiries_or_claim_an_email_was_sent(): void
    {
        config(['mail.default' => 'log']);
        Queue::fake([SendInquiryNotification::class]);
        $this->postJson('/inquiries', $this->payload())->assertCreated();
        $this->assertDatabaseHas('inquiries', ['notification_status' => 'not_configured', 'emailed_at' => null]);
        Queue::assertNothingPushed();
    }

    public static function invalidFields(): array
    {
        return [
            'missing name' => ['name', ''],
            'name too long' => ['name', str_repeat('a', 121)],
            'header injection' => ['name', "Client\r\nBcc: victim@example.com"],
            'invalid email' => ['email', 'invalid-email'],
            'phone contains script' => ['phone', '<script>123</script>'],
            'short message' => ['message', 'short'],
            'oversized message' => ['message', str_repeat('x', 3001)],
            'invalid source' => ['source', 'admin'],
            'spam trap filled' => ['website', 'https://spam.example'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_or_spam_submissions_are_not_saved(string $field, string $value): void
    {
        Queue::fake([SendInquiryNotification::class]);
        $this->postJson('/inquiries', $this->payload([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors([$field]);
        $this->assertDatabaseCount('inquiries', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_html_validation_returns_to_the_contact_form_with_values_and_errors(): void
    {
        $this->post('/inquiries', $this->payload(['email' => 'bad']))->assertRedirect(route('contact').'#contactForm')->assertSessionHasErrors('email')->assertSessionHasInput('name', 'Client Example');
        $this->withCookie(session()->getName(), session()->getId())->get('/contact')
            ->assertOk()->assertSee('value="Client Example"', false)->assertSeeText('Please check the following:');
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_submission_is_rate_limited_without_creating_additional_records(): void
    {
        Queue::fake([SendInquiryNotification::class]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/inquiries', $this->payload())->assertCreated();
        }
        $this->postJson('/inquiries', $this->payload())->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('inquiries', 5);
        Queue::assertNothingPushed();
    }

    public function test_public_forms_post_to_the_backend_and_include_csrf_and_spam_fields(): void
    {
        foreach (['/', '/contact'] as $path) {
            $this->get($path)->assertOk()->assertSee('action="'.route('inquiries.store').'"', false)->assertSee('method="post"', false)
                ->assertSee('name="_token"', false)->assertSee('name="website"', false)->assertSee('data-server-inquiry', false)->assertSeeText('Send inquiry');
        }
    }

    public function test_inquiries_are_not_exposed_through_a_public_read_endpoint(): void
    {
        $inquiry = Inquiry::factory()->create();
        $this->get('/inquiries')->assertMethodNotAllowed();
        $this->get('/inquiries/'.$inquiry->id)->assertNotFound();
    }

    public function test_submission_without_csrf_is_rejected_outside_the_test_bypass(): void
    {
        $this->app->instance('env', 'production');
        $this->postJson('/inquiries', $this->payload())->assertStatus(419);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_hourly_spam_limit_still_applies_when_minute_limits_reset(): void
    {
        Queue::fake([SendInquiryNotification::class]);
        $this->travelTo(now()->startOfSecond());
        for ($minute = 0; $minute < 4; $minute++) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->postJson('/inquiries', $this->payload())->assertCreated();
            }
            $this->travel(61)->seconds();
        }
        $this->postJson('/inquiries', $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('inquiries', 20);
        Queue::assertNothingPushed();
    }
}
