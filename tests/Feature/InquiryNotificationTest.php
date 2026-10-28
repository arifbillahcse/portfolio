<?php

namespace Tests\Feature;

use App\Jobs\SendInquiryNotification;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use App\Support\InquiryNotifier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class InquiryNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notification_uses_the_configured_sender_and_client_reply_to_then_records_delivery(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->travelTo(now()->startOfSecond());
        $inquiry = Inquiry::factory()->create(['name' => 'Client Example', 'email' => 'client@example.com']);

        $job = new SendInquiryNotification($inquiry->id, 'owner@example.com');
        $job->handle();

        Mail::assertSent(InquiryReceived::class, function ($mail) use ($inquiry): bool {
            $envelope = $mail->envelope();

            return $mail->hasTo('owner@example.com') && $mail->inquiry->is($inquiry)
                && $envelope->replyTo[0]->address === 'client@example.com'
                && $envelope->from === null;
        });
        $this->assertSame('sent', $inquiry->fresh()->notification_status);
        $this->assertTrue($inquiry->fresh()->emailed_at->equalTo(now()));

        $job->handle();
        Mail::assertSentCount(1);
    }

    public function test_terminal_failure_is_recorded_and_can_be_queued_again(): void
    {
        config(['mail.default' => 'smtp']);
        SiteSetting::factory()->create();
        $inquiry = Inquiry::factory()->create();
        $job = new SendInquiryNotification($inquiry->id, 'owner@example.com');
        $job->failed(new RuntimeException('Temporary mail failure'));
        $this->assertSame('failed', $inquiry->fresh()->notification_status);
        Queue::fake([SendInquiryNotification::class]);

        $this->assertTrue(app(InquiryNotifier::class)->queue($inquiry->fresh()));
        $this->assertSame('pending', $inquiry->fresh()->notification_status);
        Queue::assertPushed(SendInquiryNotification::class);
    }

    public function test_queue_failure_preserves_the_inquiry_and_marks_the_notification_failed(): void
    {
        config(['mail.default' => 'smtp', 'queue.default' => 'unconfigured']);
        SiteSetting::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->assertFalse(app(InquiryNotifier::class)->queue($inquiry));
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'notification_status' => 'failed']);
    }

    public function test_worker_does_not_mark_log_mail_as_delivered(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();
        $inquiry = Inquiry::factory()->create();
        (new SendInquiryNotification($inquiry->id, 'owner@example.com'))->handle();
        $this->assertSame('not_configured', $inquiry->fresh()->notification_status);
        $this->assertNull($inquiry->fresh()->emailed_at);
        Mail::assertNothingSent();
    }

    public function test_missing_inquiry_and_already_delivered_notification_are_not_resent(): void
    {
        Mail::fake();
        $inquiry = Inquiry::factory()->create(['emailed_at' => now(), 'notification_status' => 'sent']);
        (new SendInquiryNotification($inquiry->id, 'owner@example.com'))->handle();
        (new SendInquiryNotification(999999, 'owner@example.com'))->handle();
        Mail::assertNothingSent();
    }

    public function test_missing_recipient_is_reported_as_not_configured(): void
    {
        config(['mail.default' => 'smtp']);
        Queue::fake([SendInquiryNotification::class]);
        $inquiry = Inquiry::factory()->create();
        $this->assertFalse(app(InquiryNotifier::class)->queue($inquiry));
        $this->assertSame('not_configured', $inquiry->fresh()->notification_status);
        Queue::assertNothingPushed();
    }

    public function test_html_email_escapes_client_message_and_name(): void
    {
        $inquiry = Inquiry::factory()->create(['name' => '<script>alert(1)</script>', 'message' => '<img src=x onerror=alert(1)>']);
        $html = (new InquiryReceived($inquiry))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }
}
