<?php

namespace App\Jobs;

use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Support\InquiryMailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInquiryNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $inquiryId, public string $recipient) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $inquiry = Inquiry::query()->find($this->inquiryId);
        if (! $inquiry || $inquiry->emailed_at) {
            return;
        }

        $settings = app(InquiryMailSettings::class);
        $mailer = $settings->enabled() ? $settings->mailer() : null;
        if (! $mailer) {
            $inquiry->update(['notification_status' => 'not_configured']);

            return;
        }

        Mail::mailer($mailer)->to($this->recipient)->send(new InquiryReceived($inquiry));
        $inquiry->update(['notification_status' => 'sent', 'emailed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        Inquiry::query()->whereKey($this->inquiryId)->whereNull('emailed_at')->update(['notification_status' => 'failed']);
    }
}
