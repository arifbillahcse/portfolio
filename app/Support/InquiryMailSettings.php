<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;

class InquiryMailSettings
{
    public function enabled(): bool
    {
        return SiteSetting::query()->where('key', 'main')->value('lead_notifications_enabled') !== false;
    }

    public function recipient(): ?string
    {
        $settings = SiteSetting::query()->where('key', 'main')->first();
        $recipient = $settings?->lead_notification_email ?: $settings?->contact_email;

        return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
    }

    public function mailer(): ?string
    {
        $settings = SiteSetting::query()->where('key', 'main')->first();
        if (! $settings?->smtp_enabled) {
            $mailer = config('mail.default');

            return in_array($mailer, ['log', 'array'], true) ? null : $mailer;
        }

        if (! filled($settings->smtp_host) || ! filled($settings->smtp_username) || ! filled($settings->smtp_password)
            || ! filter_var($settings->smtp_from_address, FILTER_VALIDATE_EMAIL)
            || $settings->smtp_port < 1 || $settings->smtp_port > 65535
            || ! in_array($settings->smtp_encryption, ['tls', 'ssl'], true)) {
            return null;
        }

        config(['mail.mailers.portfolio_smtp' => [
            'transport' => 'smtp',
            'scheme' => $settings->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'require_tls' => true,
            'timeout' => 20,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
            'from' => ['address' => $settings->smtp_from_address, 'name' => $settings->smtp_from_name ?: $settings->brand_name],
        ]]);
        Mail::purge('portfolio_smtp');

        return 'portfolio_smtp';
    }
}
