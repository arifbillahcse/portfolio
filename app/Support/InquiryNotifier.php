<?php

namespace App\Support;

use App\Jobs\SendInquiryNotification;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Throwable;

class InquiryNotifier
{
    public function queue(Inquiry $inquiry): bool
    {
        if ($inquiry->emailed_at) {
            return false;
        }

        $recipient = SiteSetting::query()->where('key', 'main')->value('contact_email');
        if (in_array(config('mail.default'), ['log', 'array'], true) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $inquiry->update(['notification_status' => 'not_configured']);

            return false;
        }

        $inquiry->update(['notification_status' => 'pending']);
        try {
            SendInquiryNotification::dispatch($inquiry->id, $recipient)->onQueue('mail')->afterCommit();
        } catch (Throwable) {
            $inquiry->update(['notification_status' => 'failed']);
            Log::warning('Inquiry notification could not be queued.', ['inquiry_id' => $inquiry->id]);

            return false;
        }

        return true;
    }
}
