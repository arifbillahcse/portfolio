<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Jobs\SendInquiryNotification;
use App\Mail\InquiryReceived;
use App\Mail\SmtpTestMessage;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\InquiryMailSettings;
use App\Support\InquiryNotifier;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    /** @return array<string, mixed> */
    private function smtpSettings(array $overrides = []): array
    {
        return array_replace([
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.portfolio.company',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'hello@portfolio.company',
            'smtp_password' => 'SMTP-private-password',
            'smtp_from_address' => 'hello@portfolio.company',
            'smtp_from_name' => 'Arif Portfolio',
            'lead_notifications_enabled' => true,
            'lead_notification_email' => 'leads@portfolio.company',
        ], $overrides);
    }

    public function test_admin_can_save_smtp_with_an_encrypted_password_and_private_notification_recipient(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm($this->smtpSettings())
            ->call('save')->assertHasNoFormErrors()->assertSet('data.smtp_password', null);

        $settings->refresh();
        $this->assertTrue($settings->smtp_enabled);
        $this->assertSame('leads@portfolio.company', $settings->lead_notification_email);
        $this->assertSame('SMTP-private-password', $settings->smtp_password);
        $this->assertStringNotContainsString('SMTP-private-password', $settings->getRawOriginal('smtp_password'));
        $this->assertArrayNotHasKey('smtp_password', $settings->toArray());
        $this->get('/admin/site-settings/'.$settings->id.'/edit')->assertOk()->assertDontSee('SMTP-private-password');
        $this->get('/contact')->assertOk()->assertSee('owner@example.com')->assertDontSee('leads@portfolio.company')->assertDontSee('SMTP-private-password');
    }

    public function test_blank_password_preserves_the_saved_secret_and_a_new_value_replaces_it(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        $page = Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->assertSet('data.smtp_password', null);

        $page->fillForm(['smtp_password' => '', 'smtp_from_name' => 'Arif Billah'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('SMTP-private-password', $settings->fresh()->smtp_password);
        $this->assertSame('Arif Billah', $settings->fresh()->smtp_from_name);

        $page->fillForm(['smtp_password' => ' updated-secret '])->call('save')->assertHasNoFormErrors()->assertSet('data.smtp_password', null);
        $this->assertSame(' updated-secret ', $settings->fresh()->smtp_password);
    }

    public function test_enabling_smtp_requires_connection_credentials_and_sender(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm([
            'smtp_enabled' => true, 'smtp_host' => '', 'smtp_port' => null, 'smtp_encryption' => null,
            'smtp_username' => '', 'smtp_password' => '', 'smtp_from_address' => '',
        ])->call('save')->assertHasFormErrors(['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_address']);

        $this->assertFalse($settings->fresh()->smtp_enabled);
        $this->assertNull($settings->fresh()->smtp_password);
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_email_and_smtp_settings_are_rejected(string $field, mixed $value): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm($this->smtpSettings([$field => $value]))
            ->call('save')->assertHasFormErrors([$field]);

        $this->assertFalse($settings->fresh()->smtp_enabled);
        $this->assertNull($settings->fresh()->smtp_password);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidSettings(): array
    {
        return [
            'recipient' => ['lead_notification_email', 'invalid-email'],
            'host URL' => ['smtp_host', 'https://mail.portfolio.company'],
            'host options' => ['smtp_host', 'mail.portfolio.company?verify_peer=0'],
            'zero port' => ['smtp_port', 0],
            'large port' => ['smtp_port', 65536],
            'fractional port' => ['smtp_port', 587.5],
            'unencrypted' => ['smtp_encryption', 'none'],
            'sender email' => ['smtp_from_address', 'not-an-email'],
            'sender header' => ['smtp_from_name', "Arif\r\nBcc: stranger@example.com"],
            'oversized password' => ['smtp_password', str_repeat('x', 2049)],
        ];
    }

    public function test_leads_from_contact_and_home_use_the_private_recipient_and_saved_smtp(): void
    {
        SiteSetting::factory()->create($this->smtpSettings());
        Queue::fake([SendInquiryNotification::class]);

        foreach (['contact', 'home'] as $source) {
            $this->postJson('/inquiries', ['name' => 'Client', 'email' => 'client@example.com', 'phone' => '+8801779440297',
                'message' => 'Please build and deploy my new website.', 'source' => $source, 'website' => ''])->assertCreated();
        }

        $this->assertDatabaseCount('inquiries', 2);
        Queue::assertPushed(SendInquiryNotification::class, 2);
        Queue::assertPushed(SendInquiryNotification::class, fn (SendInquiryNotification $job): bool => $job->recipient === 'leads@portfolio.company' && $job->queue === 'mail');
        $inquiry = Inquiry::query()->firstOrFail();
        $this->assertSame('pending', $inquiry->notification_status);

        Mail::fake();
        (new SendInquiryNotification($inquiry->id, 'leads@portfolio.company'))->handle();

        Mail::assertSent(InquiryReceived::class, fn (InquiryReceived $mail): bool => $mail->hasTo('leads@portfolio.company') && $mail->mailer === 'portfolio_smtp');
        $this->assertSame('sent', $inquiry->fresh()->notification_status);
        $this->assertNotNull($inquiry->fresh()->emailed_at);
    }

    public function test_disabling_notifications_keeps_leads_and_stops_already_queued_emails(): void
    {
        SiteSetting::factory()->create($this->smtpSettings(['lead_notifications_enabled' => false]));
        Queue::fake([SendInquiryNotification::class]);
        Mail::fake();

        $this->postJson('/inquiries', ['name' => 'Client', 'email' => 'client@example.com', 'message' => 'Please build my new website.', 'source' => 'contact', 'website' => ''])->assertCreated();
        $inquiry = Inquiry::query()->sole();
        (new SendInquiryNotification($inquiry->id, 'leads@portfolio.company'))->handle();

        $this->assertSame('not_configured', $inquiry->fresh()->notification_status);
        $this->assertNull($inquiry->fresh()->emailed_at);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_saved_settings_replace_a_resolved_transport_without_restarting_the_worker(): void
    {
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        $mailSettings = app(InquiryMailSettings::class);
        $first = Mail::mailer($mailSettings->mailer());
        $this->assertSame('smtp.portfolio.company', $first->getSymfonyTransport()->getStream()->getHost());
        $this->assertTrue($first->getSymfonyTransport()->isTlsRequired());

        $settings->update(['smtp_host' => 'mail.changed.company', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'smtp_password' => 'updated-password']);
        $second = Mail::mailer($mailSettings->mailer());

        $this->assertNotSame($first, $second);
        $this->assertSame('mail.changed.company', $second->getSymfonyTransport()->getStream()->getHost());
        $this->assertSame(465, $second->getSymfonyTransport()->getStream()->getPort());
        $this->assertTrue($second->getSymfonyTransport()->getStream()->isTLS());
        $this->assertSame('updated-password', $second->getSymfonyTransport()->getPassword());
        $this->assertSame('array', config('mail.default'));
    }

    public function test_notification_uses_saved_sender_while_replies_go_to_the_client(): void
    {
        SiteSetting::factory()->create($this->smtpSettings(['smtp_from_name' => null, 'brand_name' => 'Arif Billah']));
        $inquiry = Inquiry::factory()->create(['name' => 'Client', 'email' => 'client@example.com']);
        $mailer = Mail::mailer(app(InquiryMailSettings::class)->mailer());
        $transport = new ArrayTransport;
        $mailer->setSymfonyTransport($transport);

        $mailer->to('leads@portfolio.company')->send(new InquiryReceived($inquiry));

        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame('hello@portfolio.company', $message->getFrom()[0]->getAddress());
        $this->assertSame('Arif Billah', $message->getFrom()[0]->getName());
        $this->assertSame('leads@portfolio.company', $message->getTo()[0]->getAddress());
        $this->assertSame('client@example.com', $message->getReplyTo()[0]->getAddress());
    }

    public function test_admin_can_send_a_test_using_saved_settings_and_the_private_recipient(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        Mail::fake();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['lead_notification_email' => 'unsaved@example.com'])
            ->callAction('testEmail')->assertNotified('Test email sent');

        Mail::assertSent(SmtpTestMessage::class, fn (SmtpTestMessage $mail): bool => $mail->hasTo('leads@portfolio.company') && ! $mail->hasTo('unsaved@example.com') && $mail->mailer === 'portfolio_smtp');
        $this->assertSame('leads@portfolio.company', $settings->fresh()->lead_notification_email);
        $this->assertStringContainsString('email delivery is working', (new SmtpTestMessage)->render());
    }

    public function test_test_email_requires_configuration(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create();
        Mail::fake();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->callAction('testEmail')->assertNotified('Email is not configured');

        Mail::assertNothingSent();
    }

    public function test_test_email_failure_does_not_expose_credentials(): void
    {
        $this->admin();
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        Mail::shouldReceive('purge')->once()->with('portfolio_smtp');
        Mail::shouldReceive('mailer')->once()->with('portfolio_smtp')->andThrow(new RuntimeException('SMTP-private-password'));

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->callAction('testEmail')
            ->assertNotified('Test email failed')->assertDontSee('SMTP-private-password');
    }

    public function test_incomplete_smtp_does_not_fall_back_to_another_transport_or_lose_leads(): void
    {
        config(['mail.default' => 'smtp']);
        SiteSetting::factory()->create($this->smtpSettings(['smtp_password' => null]));
        Queue::fake([SendInquiryNotification::class]);
        $inquiry = Inquiry::factory()->create();

        $this->assertFalse(app(InquiryNotifier::class)->queue($inquiry));

        $this->assertSame('not_configured', $inquiry->fresh()->notification_status);
        Queue::assertNothingPushed();
    }

    public function test_unreadable_smtp_secret_does_not_break_a_lead_submission(): void
    {
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        $settings->getConnection()->table('site_settings')->where('id', $settings->id)->update(['smtp_password' => 'invalid-encrypted-value']);
        Queue::fake([SendInquiryNotification::class]);

        $this->postJson('/inquiries', ['name' => 'Client', 'email' => 'client@example.com', 'message' => 'Please build my new website.', 'source' => 'home', 'website' => ''])->assertCreated();

        $this->assertDatabaseHas('inquiries', ['notification_status' => 'failed']);
        Queue::assertNothingPushed();
    }

    public function test_regular_users_cannot_open_smtp_settings_or_the_test_email_action(): void
    {
        $settings = SiteSetting::factory()->create($this->smtpSettings());
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        Filament::setCurrentPanel('admin');
        Mail::fake();

        $this->get('/admin/site-settings/'.$settings->id.'/edit')->assertForbidden();
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->assertForbidden();

        Mail::assertNothingSent();
    }
}
