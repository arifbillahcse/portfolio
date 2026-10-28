<?php

namespace App\Support;

use App\Jobs\SendInquiryNotification;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Log;
use Throwable;

class InquiryNotifier
{
    public function queue(Inquiry $inquiry): bool
    {
        if ($inquiry->emailed_at) {
            return false;
        }

        try {
            $settings = app(InquiryMailSettings::class);
            $recipient = $settings->recipient();
            if (! $settings->enabled() || ! $recipient || ! $settings->mailer()) {
                $inquiry->update(['notification_status' => 'not_configured']);

                return false;
            }

            $inquiry->update(['notification_status' => 'pending']);
            SendInquiryNotification::dispatch($inquiry->id, $recipient)->onQueue('mail')->afterCommit();
        } catch (Throwable) {
            $inquiry->update(['notification_status' => 'failed']);
            Log::warning('Inquiry notification could not be queued.', ['inquiry_id' => $inquiry->id]);

            return false;
        }

        return true;
    }
}
